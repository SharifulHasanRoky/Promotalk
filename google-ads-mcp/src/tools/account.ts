import { getCustomer, formatMicros } from "../utils/google-ads-client.js";

export const accountTools = [
  {
    name: "account_info",
    description: "Get Google Ads account information including ID, name, currency, and timezone",
    inputSchema: {
      type: "object" as const,
      properties: {},
    },
  },
  {
    name: "account_hierarchy",
    description: "Get account hierarchy (for MCC accounts) showing linked customer accounts",
    inputSchema: {
      type: "object" as const,
      properties: {},
    },
  },
];

export async function handleAccountTool(name: string, args: any) {
  const customer = getCustomer();

  switch (name) {
    case "account_info": return await getAccountInfo(customer);
    case "account_hierarchy": return await getAccountHierarchy(customer);
    default:
      return { content: [{ type: "text", text: `Unknown account tool: ${name}` }], isError: true };
  }
}

async function getAccountInfo(customer: any) {
  const query = `
    SELECT
      customer.id,
      customer.descriptive_name,
      customer.currency_code,
      customer.time_zone,
      customer.auto_tagging_enabled,
      customer.manager,
      customer.status
    FROM customer
    LIMIT 1
  `;

  const results = await customer.query(query);

  if (results.length === 0) {
    return { content: [{ type: "text", text: "Unable to fetch account info" }], isError: true };
  }

  const row = results[0];
  const info = {
    customer_id: row.customer.id,
    name: row.customer.descriptive_name,
    currency: row.customer.currency_code,
    timezone: row.customer.time_zone,
    auto_tagging: row.customer.auto_tagging_enabled,
    is_manager: row.customer.manager,
    status: row.customer.status,
  };

  return {
    content: [{ type: "text", text: JSON.stringify({ account: info }, null, 2) }],
  };
}

async function getAccountHierarchy(customer: any) {
  const query = `
    SELECT
      customer_client.id,
      customer_client.descriptive_name,
      customer_client.currency_code,
      customer_client.time_zone,
      customer_client.manager,
      customer_client.status,
      customer_client.level
    FROM customer_client
    ORDER BY customer_client.level, customer_client.descriptive_name
  `;

  const results = await customer.query(query);

  const accounts = results.map((row: any) => ({
    id: row.customer_client.id,
    name: row.customer_client.descriptive_name,
    currency: row.customer_client.currency_code,
    timezone: row.customer_client.time_zone,
    is_manager: row.customer_client.manager,
    status: row.customer_client.status,
    level: row.customer_client.level,
  }));

  return {
    content: [{ type: "text", text: JSON.stringify({ hierarchy: accounts, total: accounts.length }, null, 2) }],
  };
}
