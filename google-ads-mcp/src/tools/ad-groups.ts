import { getCustomer, formatMicros, toMicros } from "../utils/google-ads-client.js";

export const adGroupTools = [
  {
    name: "adgroup_list",
    description: "List all ad groups for a specific campaign or all campaigns",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: {
          type: "string",
          description: "Filter ad groups by campaign ID. Optional - lists all if not specified",
        },
        status: {
          type: "string",
          enum: ["ENABLED", "PAUSED", "REMOVED", "ALL"],
          description: "Filter by status. Default: ALL",
        },
        limit: {
          type: "number",
          description: "Maximum number of ad groups to return. Default: 50",
        },
      },
    },
  },
  {
    name: "adgroup_get",
    description: "Get detailed information about a specific ad group",
    inputSchema: {
      type: "object" as const,
      properties: {
        ad_group_id: {
          type: "string",
          description: "The ad group ID to fetch details for",
        },
      },
      required: ["ad_group_id"],
    },
  },
  {
    name: "adgroup_create",
    description: "Create a new ad group within a campaign",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: {
          type: "string",
          description: "The campaign ID to create the ad group in",
        },
        name: {
          type: "string",
          description: "Ad group name",
        },
        cpc_bid: {
          type: "number",
          description: "Default max CPC bid amount in account currency (e.g., 2.50 for $2.50)",
        },
        type: {
          type: "string",
          enum: ["SEARCH_STANDARD", "DISPLAY_STANDARD", "SHOPPING_PRODUCT_ADS", "VIDEO_BUMPER"],
          description: "Ad group type. Default: SEARCH_STANDARD",
        },
      },
      required: ["campaign_id", "name"],
    },
  },
  {
    name: "adgroup_update",
    description: "Update an existing ad group",
    inputSchema: {
      type: "object" as const,
      properties: {
        ad_group_id: {
          type: "string",
          description: "The ad group ID to update",
        },
        name: {
          type: "string",
          description: "New ad group name",
        },
        status: {
          type: "string",
          enum: ["ENABLED", "PAUSED"],
          description: "New ad group status",
        },
        cpc_bid: {
          type: "number",
          description: "New default max CPC bid amount",
        },
      },
      required: ["ad_group_id"],
    },
  },
  {
    name: "adgroup_pause",
    description: "Pause an ad group",
    inputSchema: {
      type: "object" as const,
      properties: {
        ad_group_id: {
          type: "string",
          description: "The ad group ID to pause",
        },
      },
      required: ["ad_group_id"],
    },
  },
  {
    name: "adgroup_enable",
    description: "Enable/resume a paused ad group",
    inputSchema: {
      type: "object" as const,
      properties: {
        ad_group_id: {
          type: "string",
          description: "The ad group ID to enable",
        },
      },
      required: ["ad_group_id"],
    },
  },
];

export async function handleAdGroupTool(name: string, args: any) {
  const customer = getCustomer();

  switch (name) {
    case "adgroup_list":
      return await listAdGroups(customer, args);
    case "adgroup_get":
      return await getAdGroup(customer, args);
    case "adgroup_create":
      return await createAdGroup(customer, args);
    case "adgroup_update":
      return await updateAdGroup(customer, args);
    case "adgroup_pause":
      return await pauseAdGroup(customer, args);
    case "adgroup_enable":
      return await enableAdGroup(customer, args);
    default:
      return { content: [{ type: "text", text: `Unknown ad group tool: ${name}` }], isError: true };
  }
}

async function listAdGroups(customer: any, args: any) {
  const { campaign_id, status = "ALL", limit = 50 } = args || {};

  let query = `
    SELECT
      ad_group.id,
      ad_group.name,
      ad_group.status,
      ad_group.type,
      ad_group.cpc_bid_micros,
      campaign.id,
      campaign.name,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.ctr
    FROM ad_group
  `;

  const conditions: string[] = [];
  if (campaign_id) conditions.push(`campaign.id = '${campaign_id}'`);
  if (status !== "ALL") conditions.push(`ad_group.status = '${status}'`);

  if (conditions.length > 0) {
    query += ` WHERE ${conditions.join(" AND ")}`;
  }

  query += ` ORDER BY ad_group.name LIMIT ${limit}`;

  const results = await customer.query(query);

  const adGroups = results.map((row: any) => ({
    id: row.ad_group.id,
    name: row.ad_group.name,
    status: row.ad_group.status,
    type: row.ad_group.type,
    cpc_bid: formatMicros(row.ad_group.cpc_bid_micros || 0),
    campaign_id: row.campaign.id,
    campaign_name: row.campaign.name,
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
        text: JSON.stringify({ ad_groups: adGroups, total: adGroups.length }, null, 2),
      },
    ],
  };
}

async function getAdGroup(customer: any, args: any) {
  const { ad_group_id } = args;

  const query = `
    SELECT
      ad_group.id,
      ad_group.name,
      ad_group.status,
      ad_group.type,
      ad_group.cpc_bid_micros,
      campaign.id,
      campaign.name,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.ctr,
      metrics.average_cpc
    FROM ad_group
    WHERE ad_group.id = '${ad_group_id}'
  `;

  const results = await customer.query(query);

  if (results.length === 0) {
    return { content: [{ type: "text", text: `Ad group ${ad_group_id} not found` }], isError: true };
  }

  const row = results[0];
  const adGroup = {
    id: row.ad_group.id,
    name: row.ad_group.name,
    status: row.ad_group.status,
    type: row.ad_group.type,
    cpc_bid: formatMicros(row.ad_group.cpc_bid_micros || 0),
    campaign: { id: row.campaign.id, name: row.campaign.name },
    metrics: {
      impressions: row.metrics.impressions,
      clicks: row.metrics.clicks,
      cost: formatMicros(row.metrics.cost_micros),
      conversions: row.metrics.conversions,
      ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
      avg_cpc: formatMicros(row.metrics.average_cpc),
    },
  };

  return {
    content: [{ type: "text", text: JSON.stringify(adGroup, null, 2) }],
  };
}

async function createAdGroup(customer: any, args: any) {
  const { campaign_id, name, cpc_bid, type = "SEARCH_STANDARD" } = args;

  const adGroupData: any = {
    campaign: `customers/${customer.credentials.customer_id}/campaigns/${campaign_id}`,
    name,
    type,
    status: "ENABLED",
  };

  if (cpc_bid) {
    adGroupData.cpc_bid_micros = toMicros(cpc_bid);
  }

  const result = await customer.adGroups.create([adGroupData]);

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify(
          {
            success: true,
            message: `Ad group "${name}" created successfully in campaign ${campaign_id}`,
            resource_name: result.results[0].resource_name,
          },
          null,
          2
        ),
      },
    ],
  };
}

async function updateAdGroup(customer: any, args: any) {
  const { ad_group_id, name, status, cpc_bid } = args;

  const updateData: any = {
    resource_name: `customers/${customer.credentials.customer_id}/adGroups/${ad_group_id}`,
  };

  if (name) updateData.name = name;
  if (status) updateData.status = status === "ENABLED" ? 2 : 3;
  if (cpc_bid) updateData.cpc_bid_micros = toMicros(cpc_bid);

  await customer.adGroups.update([updateData]);

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify(
          {
            success: true,
            message: `Ad group ${ad_group_id} updated successfully`,
            updates: { name, status, cpc_bid },
          },
          null,
          2
        ),
      },
    ],
  };
}

async function pauseAdGroup(customer: any, args: any) {
  const { ad_group_id } = args;

  await customer.adGroups.update([
    {
      resource_name: `customers/${customer.credentials.customer_id}/adGroups/${ad_group_id}`,
      status: 3,
    },
  ]);

  return {
    content: [
      { type: "text", text: JSON.stringify({ success: true, message: `Ad group ${ad_group_id} paused` }, null, 2) },
    ],
  };
}

async function enableAdGroup(customer: any, args: any) {
  const { ad_group_id } = args;

  await customer.adGroups.update([
    {
      resource_name: `customers/${customer.credentials.customer_id}/adGroups/${ad_group_id}`,
      status: 2,
    },
  ]);

  return {
    content: [
      { type: "text", text: JSON.stringify({ success: true, message: `Ad group ${ad_group_id} enabled` }, null, 2) },
    ],
  };
}
