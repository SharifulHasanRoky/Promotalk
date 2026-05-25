import { getCustomer, formatMicros, toMicros } from "../utils/google-ads-client.js";

export const budgetTools = [
  {
    name: "budget_list",
    description: "List all campaign budgets in the account",
    inputSchema: {
      type: "object" as const,
      properties: {
        limit: { type: "number", description: "Max results. Default: 50" },
      },
    },
  },
  {
    name: "budget_get",
    description: "Get details of a specific campaign budget",
    inputSchema: {
      type: "object" as const,
      properties: {
        budget_id: { type: "string", description: "Budget resource ID" },
      },
      required: ["budget_id"],
    },
  },
  {
    name: "budget_update",
    description: "Update a campaign budget amount",
    inputSchema: {
      type: "object" as const,
      properties: {
        budget_id: { type: "string", description: "Budget resource ID" },
        amount: { type: "number", description: "New daily budget amount in account currency" },
      },
      required: ["budget_id", "amount"],
    },
  },
  {
    name: "budget_create",
    description: "Create a new shared campaign budget",
    inputSchema: {
      type: "object" as const,
      properties: {
        name: { type: "string", description: "Budget name" },
        amount: { type: "number", description: "Daily budget amount in account currency" },
        shared: { type: "boolean", description: "Whether this budget can be shared across campaigns. Default: false" },
      },
      required: ["name", "amount"],
    },
  },
  {
    name: "budget_spending_summary",
    description: "Get budget spending analysis - how much has been spent vs budget for each campaign",
    inputSchema: {
      type: "object" as const,
      properties: {
        date_range: {
          type: "string",
          enum: ["TODAY", "YESTERDAY", "LAST_7_DAYS", "LAST_30_DAYS", "THIS_MONTH"],
          description: "Date range for analysis. Default: THIS_MONTH",
        },
      },
    },
  },
];

export async function handleBudgetTool(name: string, args: any) {
  const customer = getCustomer();

  switch (name) {
    case "budget_list": return await listBudgets(customer, args);
    case "budget_get": return await getBudget(customer, args);
    case "budget_update": return await updateBudget(customer, args);
    case "budget_create": return await createBudget(customer, args);
    case "budget_spending_summary": return await spendingSummary(customer, args);
    default:
      return { content: [{ type: "text", text: `Unknown budget tool: ${name}` }], isError: true };
  }
}

async function listBudgets(customer: any, args: any) {
  const { limit = 50 } = args || {};

  const query = `
    SELECT
      campaign_budget.id,
      campaign_budget.name,
      campaign_budget.amount_micros,
      campaign_budget.delivery_method,
      campaign_budget.explicitly_shared,
      campaign_budget.status,
      campaign_budget.total_amount_micros
    FROM campaign_budget
    ORDER BY campaign_budget.name
    LIMIT ${limit}
  `;

  const results = await customer.query(query);

  const budgets = results.map((row: any) => ({
    id: row.campaign_budget.id,
    name: row.campaign_budget.name,
    daily_amount: formatMicros(row.campaign_budget.amount_micros),
    delivery_method: row.campaign_budget.delivery_method,
    shared: row.campaign_budget.explicitly_shared,
    status: row.campaign_budget.status,
    total_amount: row.campaign_budget.total_amount_micros
      ? formatMicros(row.campaign_budget.total_amount_micros)
      : null,
  }));

  return {
    content: [{ type: "text", text: JSON.stringify({ budgets, total: budgets.length }, null, 2) }],
  };
}

async function getBudget(customer: any, args: any) {
  const { budget_id } = args;

  const query = `
    SELECT
      campaign_budget.id,
      campaign_budget.name,
      campaign_budget.amount_micros,
      campaign_budget.delivery_method,
      campaign_budget.explicitly_shared,
      campaign_budget.status,
      campaign_budget.total_amount_micros,
      campaign_budget.reference_count
    FROM campaign_budget
    WHERE campaign_budget.id = '${budget_id}'
  `;

  const results = await customer.query(query);

  if (results.length === 0) {
    return { content: [{ type: "text", text: `Budget ${budget_id} not found` }], isError: true };
  }

  const row = results[0];
  const budget = {
    id: row.campaign_budget.id,
    name: row.campaign_budget.name,
    daily_amount: formatMicros(row.campaign_budget.amount_micros),
    delivery_method: row.campaign_budget.delivery_method,
    shared: row.campaign_budget.explicitly_shared,
    status: row.campaign_budget.status,
    campaigns_using: row.campaign_budget.reference_count,
  };

  return {
    content: [{ type: "text", text: JSON.stringify(budget, null, 2) }],
  };
}

async function updateBudget(customer: any, args: any) {
  const { budget_id, amount } = args;

  await customer.campaignBudgets.update([{
    resource_name: `customers/${customer.credentials.customer_id}/campaignBudgets/${budget_id}`,
    amount_micros: toMicros(amount),
  }]);

  return {
    content: [{
      type: "text",
      text: JSON.stringify({
        success: true,
        message: `Budget ${budget_id} updated to ${amount}/day`,
      }, null, 2),
    }],
  };
}

async function createBudget(customer: any, args: any) {
  const { name, amount, shared = false } = args;

  const result = await customer.campaignBudgets.create([{
    name,
    amount_micros: toMicros(amount),
    delivery_method: "STANDARD",
    explicitly_shared: shared,
  }]);

  return {
    content: [{
      type: "text",
      text: JSON.stringify({
        success: true,
        message: `Budget "${name}" created with ${amount}/day`,
        resource_name: result.results[0].resource_name,
        shared,
      }, null, 2),
    }],
  };
}

async function spendingSummary(customer: any, args: any) {
  const { date_range = "THIS_MONTH" } = args || {};

  const query = `
    SELECT
      campaign.id,
      campaign.name,
      campaign.status,
      campaign_budget.amount_micros,
      metrics.cost_micros,
      metrics.clicks,
      metrics.conversions
    FROM campaign
    WHERE segments.date DURING ${date_range}
      AND campaign.status != 'REMOVED'
    ORDER BY metrics.cost_micros DESC
  `;

  const results = await customer.query(query);

  const spending = results.map((row: any) => {
    const budget = row.campaign_budget.amount_micros;
    const spent = row.metrics.cost_micros;
    return {
      campaign_id: row.campaign.id,
      campaign_name: row.campaign.name,
      status: row.campaign.status,
      daily_budget: formatMicros(budget),
      total_spent: formatMicros(spent),
      clicks: row.metrics.clicks,
      conversions: row.metrics.conversions,
    };
  });

  const totalSpent = results.reduce((sum: number, r: any) => sum + r.metrics.cost_micros, 0);
  const totalBudget = results.reduce((sum: number, r: any) => sum + r.campaign_budget.amount_micros, 0);

  return {
    content: [{
      type: "text",
      text: JSON.stringify({
        date_range,
        total_spent: formatMicros(totalSpent),
        total_daily_budget: formatMicros(totalBudget),
        campaigns: spending,
      }, null, 2),
    }],
  };
}
