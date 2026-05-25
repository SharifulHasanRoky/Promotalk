import { getCustomer, formatMicros, toMicros } from "../utils/google-ads-client.js";

export const campaignTools = [
  {
    name: "campaign_list",
    description: "List all campaigns in the Google Ads account with their status, budget, and basic metrics",
    inputSchema: {
      type: "object" as const,
      properties: {
        status: {
          type: "string",
          enum: ["ENABLED", "PAUSED", "REMOVED", "ALL"],
          description: "Filter campaigns by status. Default: ALL",
        },
        limit: {
          type: "number",
          description: "Maximum number of campaigns to return. Default: 50",
        },
      },
    },
  },
  {
    name: "campaign_get",
    description: "Get detailed information about a specific campaign by ID",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: {
          type: "string",
          description: "The campaign ID to fetch details for",
        },
      },
      required: ["campaign_id"],
    },
  },
  {
    name: "campaign_create",
    description: "Create a new Google Ads campaign",
    inputSchema: {
      type: "object" as const,
      properties: {
        name: {
          type: "string",
          description: "Campaign name",
        },
        budget_amount: {
          type: "number",
          description: "Daily budget amount in account currency (e.g., 50.00 for $50)",
        },
        advertising_channel_type: {
          type: "string",
          enum: ["SEARCH", "DISPLAY", "SHOPPING", "VIDEO", "PERFORMANCE_MAX"],
          description: "The advertising channel type. Default: SEARCH",
        },
        bidding_strategy: {
          type: "string",
          enum: ["MANUAL_CPC", "MAXIMIZE_CLICKS", "MAXIMIZE_CONVERSIONS", "TARGET_CPA", "TARGET_ROAS"],
          description: "Bidding strategy for the campaign. Default: MAXIMIZE_CLICKS",
        },
        target_cpa: {
          type: "number",
          description: "Target CPA amount (required if bidding_strategy is TARGET_CPA)",
        },
        target_roas: {
          type: "number",
          description: "Target ROAS value (required if bidding_strategy is TARGET_ROAS)",
        },
        start_date: {
          type: "string",
          description: "Campaign start date in YYYY-MM-DD format. Default: today",
        },
        end_date: {
          type: "string",
          description: "Campaign end date in YYYY-MM-DD format. Optional",
        },
        network_settings: {
          type: "object",
          properties: {
            target_google_search: { type: "boolean" },
            target_search_network: { type: "boolean" },
            target_content_network: { type: "boolean" },
          },
          description: "Network targeting settings",
        },
      },
      required: ["name", "budget_amount"],
    },
  },
  {
    name: "campaign_update",
    description: "Update an existing campaign's settings",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: {
          type: "string",
          description: "The campaign ID to update",
        },
        name: {
          type: "string",
          description: "New campaign name",
        },
        status: {
          type: "string",
          enum: ["ENABLED", "PAUSED"],
          description: "New campaign status",
        },
        budget_amount: {
          type: "number",
          description: "New daily budget amount in account currency",
        },
      },
      required: ["campaign_id"],
    },
  },
  {
    name: "campaign_pause",
    description: "Pause a running campaign",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: {
          type: "string",
          description: "The campaign ID to pause",
        },
      },
      required: ["campaign_id"],
    },
  },
  {
    name: "campaign_enable",
    description: "Enable/resume a paused campaign",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: {
          type: "string",
          description: "The campaign ID to enable",
        },
      },
      required: ["campaign_id"],
    },
  },
  {
    name: "campaign_delete",
    description: "Delete (remove) a campaign. This sets the campaign status to REMOVED.",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: {
          type: "string",
          description: "The campaign ID to delete",
        },
      },
      required: ["campaign_id"],
    },
  },
];

export async function handleCampaignTool(name: string, args: any) {
  const customer = getCustomer();

  switch (name) {
    case "campaign_list":
      return await listCampaigns(customer, args);
    case "campaign_get":
      return await getCampaign(customer, args);
    case "campaign_create":
      return await createCampaign(customer, args);
    case "campaign_update":
      return await updateCampaign(customer, args);
    case "campaign_pause":
      return await pauseCampaign(customer, args);
    case "campaign_enable":
      return await enableCampaign(customer, args);
    case "campaign_delete":
      return await deleteCampaign(customer, args);
    default:
      return { content: [{ type: "text", text: `Unknown campaign tool: ${name}` }], isError: true };
  }
}

async function listCampaigns(customer: any, args: any) {
  const status = args?.status || "ALL";
  const limit = args?.limit || 50;

  let query = `
    SELECT
      campaign.id,
      campaign.name,
      campaign.status,
      campaign.advertising_channel_type,
      campaign.bidding_strategy_type,
      campaign_budget.amount_micros,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.ctr
    FROM campaign
  `;

  if (status !== "ALL") {
    query += ` WHERE campaign.status = '${status}'`;
  }

  query += ` ORDER BY campaign.name LIMIT ${limit}`;

  const campaigns = await customer.query(query);

  const results = campaigns.map((row: any) => ({
    id: row.campaign.id,
    name: row.campaign.name,
    status: row.campaign.status,
    channel_type: row.campaign.advertising_channel_type,
    bidding_strategy: row.campaign.bidding_strategy_type,
    daily_budget: formatMicros(row.campaign_budget.amount_micros),
    impressions: row.metrics.impressions,
    clicks: row.metrics.clicks,
    cost: formatMicros(row.metrics.cost_micros),
    conversions: row.metrics.conversions,
    ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
  }));

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify({ campaigns: results, total: results.length }, null, 2),
      },
    ],
  };
}

async function getCampaign(customer: any, args: any) {
  const { campaign_id } = args;

  const query = `
    SELECT
      campaign.id,
      campaign.name,
      campaign.status,
      campaign.advertising_channel_type,
      campaign.bidding_strategy_type,
      campaign.start_date,
      campaign.end_date,
      campaign.network_settings.target_google_search,
      campaign.network_settings.target_search_network,
      campaign.network_settings.target_content_network,
      campaign_budget.amount_micros,
      campaign_budget.delivery_method,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.ctr,
      metrics.average_cpc,
      metrics.average_cpm
    FROM campaign
    WHERE campaign.id = '${campaign_id}'
  `;

  const results = await customer.query(query);

  if (results.length === 0) {
    return { content: [{ type: "text", text: `Campaign ${campaign_id} not found` }], isError: true };
  }

  const row = results[0];
  const campaign = {
    id: row.campaign.id,
    name: row.campaign.name,
    status: row.campaign.status,
    channel_type: row.campaign.advertising_channel_type,
    bidding_strategy: row.campaign.bidding_strategy_type,
    start_date: row.campaign.start_date,
    end_date: row.campaign.end_date,
    network_settings: {
      target_google_search: row.campaign.network_settings?.target_google_search,
      target_search_network: row.campaign.network_settings?.target_search_network,
      target_content_network: row.campaign.network_settings?.target_content_network,
    },
    budget: {
      daily_amount: formatMicros(row.campaign_budget.amount_micros),
      delivery_method: row.campaign_budget.delivery_method,
    },
    metrics: {
      impressions: row.metrics.impressions,
      clicks: row.metrics.clicks,
      cost: formatMicros(row.metrics.cost_micros),
      conversions: row.metrics.conversions,
      ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
      avg_cpc: formatMicros(row.metrics.average_cpc),
      avg_cpm: formatMicros(row.metrics.average_cpm),
    },
  };

  return {
    content: [{ type: "text", text: JSON.stringify(campaign, null, 2) }],
  };
}

async function createCampaign(customer: any, args: any) {
  const {
    name,
    budget_amount,
    advertising_channel_type = "SEARCH",
    bidding_strategy = "MAXIMIZE_CLICKS",
    target_cpa,
    target_roas,
    start_date,
    end_date,
    network_settings,
  } = args;

  // First create the budget
  const budgetResult = await customer.campaignBudgets.create([
    {
      name: `${name} Budget`,
      amount_micros: toMicros(budget_amount),
      delivery_method: "STANDARD",
      explicitly_shared: false,
    },
  ]);

  const budgetResourceName = budgetResult.results[0].resource_name;

  // Build campaign object
  const campaignData: any = {
    name,
    campaign_budget: budgetResourceName,
    advertising_channel_type,
    status: "PAUSED", // Start paused for safety
    network_settings: {
      target_google_search: network_settings?.target_google_search ?? true,
      target_search_network: network_settings?.target_search_network ?? true,
      target_content_network: network_settings?.target_content_network ?? false,
    },
  };

  // Set bidding strategy
  switch (bidding_strategy) {
    case "MANUAL_CPC":
      campaignData.manual_cpc = { enhanced_cpc_enabled: true };
      break;
    case "MAXIMIZE_CLICKS":
      campaignData.maximize_clicks = {};
      break;
    case "MAXIMIZE_CONVERSIONS":
      campaignData.maximize_conversions = {};
      break;
    case "TARGET_CPA":
      campaignData.target_cpa = { target_cpa_micros: toMicros(target_cpa || 10) };
      break;
    case "TARGET_ROAS":
      campaignData.target_roas = { target_roas: target_roas || 4.0 };
      break;
  }

  if (start_date) campaignData.start_date = start_date.replace(/-/g, "");
  if (end_date) campaignData.end_date = end_date.replace(/-/g, "");

  const result = await customer.campaigns.create([campaignData]);

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify(
          {
            success: true,
            message: `Campaign "${name}" created successfully (status: PAUSED)`,
            resource_name: result.results[0].resource_name,
            budget_resource_name: budgetResourceName,
            note: "Campaign created in PAUSED status. Use campaign_enable to activate it.",
          },
          null,
          2
        ),
      },
    ],
  };
}

async function updateCampaign(customer: any, args: any) {
  const { campaign_id, name, status, budget_amount } = args;

  const updateData: any = {
    resource_name: `customers/${customer.credentials.customer_id}/campaigns/${campaign_id}`,
  };

  const updateMask: string[] = [];

  if (name) {
    updateData.name = name;
    updateMask.push("name");
  }

  if (status) {
    updateData.status = status === "ENABLED" ? 2 : 3;
    updateMask.push("status");
  }

  if (budget_amount) {
    // Get current budget resource name
    const query = `SELECT campaign.campaign_budget FROM campaign WHERE campaign.id = '${campaign_id}'`;
    const results = await customer.query(query);
    if (results.length > 0) {
      await customer.campaignBudgets.update([
        {
          resource_name: results[0].campaign.campaign_budget,
          amount_micros: toMicros(budget_amount),
        },
      ]);
    }
  }

  if (updateMask.length > 0) {
    await customer.campaigns.update([updateData]);
  }

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify(
          {
            success: true,
            message: `Campaign ${campaign_id} updated successfully`,
            updates: { name, status, budget_amount },
          },
          null,
          2
        ),
      },
    ],
  };
}

async function pauseCampaign(customer: any, args: any) {
  const { campaign_id } = args;

  await customer.campaigns.update([
    {
      resource_name: `customers/${customer.credentials.customer_id}/campaigns/${campaign_id}`,
      status: 3, // PAUSED
    },
  ]);

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify({ success: true, message: `Campaign ${campaign_id} paused successfully` }, null, 2),
      },
    ],
  };
}

async function enableCampaign(customer: any, args: any) {
  const { campaign_id } = args;

  await customer.campaigns.update([
    {
      resource_name: `customers/${customer.credentials.customer_id}/campaigns/${campaign_id}`,
      status: 2, // ENABLED
    },
  ]);

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify({ success: true, message: `Campaign ${campaign_id} enabled successfully` }, null, 2),
      },
    ],
  };
}

async function deleteCampaign(customer: any, args: any) {
  const { campaign_id } = args;

  await customer.campaigns.update([
    {
      resource_name: `customers/${customer.credentials.customer_id}/campaigns/${campaign_id}`,
      status: 4, // REMOVED
    },
  ]);

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify({ success: true, message: `Campaign ${campaign_id} removed successfully` }, null, 2),
      },
    ],
  };
}
