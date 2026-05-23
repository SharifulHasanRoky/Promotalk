import { getCustomer, formatMicros } from "../utils/google-ads-client.js";

export const reportTools = [
  {
    name: "report_campaign_performance",
    description: "Get campaign performance report with detailed metrics for a date range",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: { type: "string", description: "Optional campaign ID. If omitted, returns all campaigns" },
        date_range: {
          type: "string",
          enum: ["TODAY", "YESTERDAY", "LAST_7_DAYS", "LAST_30_DAYS", "THIS_MONTH", "LAST_MONTH", "CUSTOM"],
          description: "Date range for the report. Default: LAST_30_DAYS",
        },
        start_date: { type: "string", description: "Start date (YYYY-MM-DD) for CUSTOM range" },
        end_date: { type: "string", description: "End date (YYYY-MM-DD) for CUSTOM range" },
      },
    },
  },
  {
    name: "report_ad_group_performance",
    description: "Get ad group performance metrics",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: { type: "string", description: "Filter by campaign ID" },
        date_range: {
          type: "string",
          enum: ["TODAY", "YESTERDAY", "LAST_7_DAYS", "LAST_30_DAYS", "THIS_MONTH", "LAST_MONTH"],
          description: "Date range. Default: LAST_30_DAYS",
        },
        limit: { type: "number", description: "Max results. Default: 50" },
      },
    },
  },
  {
    name: "report_keyword_performance",
    description: "Get keyword performance metrics with quality scores",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: { type: "string", description: "Filter by campaign ID" },
        ad_group_id: { type: "string", description: "Filter by ad group ID" },
        date_range: {
          type: "string",
          enum: ["TODAY", "YESTERDAY", "LAST_7_DAYS", "LAST_30_DAYS", "THIS_MONTH", "LAST_MONTH"],
          description: "Date range. Default: LAST_30_DAYS",
        },
        order_by: {
          type: "string",
          enum: ["clicks", "impressions", "cost", "conversions", "ctr"],
          description: "Order results by metric. Default: clicks",
        },
        limit: { type: "number", description: "Max results. Default: 50" },
      },
    },
  },
  {
    name: "report_daily_stats",
    description: "Get daily performance stats broken down by date",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: { type: "string", description: "Campaign ID (required)" },
        date_range: {
          type: "string",
          enum: ["LAST_7_DAYS", "LAST_14_DAYS", "LAST_30_DAYS", "THIS_MONTH", "LAST_MONTH"],
          description: "Date range. Default: LAST_30_DAYS",
        },
      },
      required: ["campaign_id"],
    },
  },
  {
    name: "report_search_terms",
    description: "Get search terms report showing actual queries that triggered ads",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: { type: "string", description: "Filter by campaign ID" },
        ad_group_id: { type: "string", description: "Filter by ad group ID" },
        date_range: {
          type: "string",
          enum: ["LAST_7_DAYS", "LAST_30_DAYS", "THIS_MONTH", "LAST_MONTH"],
          description: "Date range. Default: LAST_30_DAYS",
        },
        limit: { type: "number", description: "Max results. Default: 100" },
      },
    },
  },
  {
    name: "report_account_summary",
    description: "Get a high-level account performance summary",
    inputSchema: {
      type: "object" as const,
      properties: {
        date_range: {
          type: "string",
          enum: ["TODAY", "YESTERDAY", "LAST_7_DAYS", "LAST_30_DAYS", "THIS_MONTH", "LAST_MONTH"],
          description: "Date range. Default: LAST_30_DAYS",
        },
      },
    },
  },
];

export async function handleReportTool(name: string, args: any) {
  const customer = getCustomer();

  switch (name) {
    case "report_campaign_performance": return await campaignPerformance(customer, args);
    case "report_ad_group_performance": return await adGroupPerformance(customer, args);
    case "report_keyword_performance": return await keywordPerformance(customer, args);
    case "report_daily_stats": return await dailyStats(customer, args);
    case "report_search_terms": return await searchTerms(customer, args);
    case "report_account_summary": return await accountSummary(customer, args);
    default:
      return { content: [{ type: "text", text: `Unknown report tool: ${name}` }], isError: true };
  }
}

function getDateClause(dateRange: string, startDate?: string, endDate?: string): string {
  if (dateRange === "CUSTOM" && startDate && endDate) {
    return `segments.date BETWEEN '${startDate}' AND '${endDate}'`;
  }
  return `segments.date DURING ${dateRange || "LAST_30_DAYS"}`;
}

async function campaignPerformance(customer: any, args: any) {
  const { campaign_id, date_range = "LAST_30_DAYS", start_date, end_date } = args || {};

  let query = `
    SELECT
      campaign.id,
      campaign.name,
      campaign.status,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.conversions_value,
      metrics.ctr,
      metrics.average_cpc,
      metrics.cost_per_conversion,
      metrics.interaction_rate
    FROM campaign
    WHERE ${getDateClause(date_range, start_date, end_date)}
  `;

  if (campaign_id) query += ` AND campaign.id = '${campaign_id}'`;
  query += ` ORDER BY metrics.cost_micros DESC`;

  const results = await customer.query(query);

  const report = results.map((row: any) => ({
    campaign_id: row.campaign.id,
    campaign_name: row.campaign.name,
    status: row.campaign.status,
    impressions: row.metrics.impressions,
    clicks: row.metrics.clicks,
    cost: formatMicros(row.metrics.cost_micros),
    conversions: row.metrics.conversions,
    conversion_value: formatMicros(row.metrics.conversions_value || 0),
    ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
    avg_cpc: formatMicros(row.metrics.average_cpc),
    cost_per_conversion: formatMicros(row.metrics.cost_per_conversion || 0),
  }));

  return {
    content: [{ type: "text", text: JSON.stringify({ date_range, report, total_campaigns: report.length }, null, 2) }],
  };
}

async function adGroupPerformance(customer: any, args: any) {
  const { campaign_id, date_range = "LAST_30_DAYS", limit = 50 } = args || {};

  let query = `
    SELECT
      ad_group.id,
      ad_group.name,
      campaign.name,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.ctr,
      metrics.average_cpc
    FROM ad_group
    WHERE ${getDateClause(date_range)}
  `;

  if (campaign_id) query += ` AND campaign.id = '${campaign_id}'`;
  query += ` ORDER BY metrics.cost_micros DESC LIMIT ${limit}`;

  const results = await customer.query(query);

  const report = results.map((row: any) => ({
    ad_group_id: row.ad_group.id,
    ad_group_name: row.ad_group.name,
    campaign_name: row.campaign.name,
    impressions: row.metrics.impressions,
    clicks: row.metrics.clicks,
    cost: formatMicros(row.metrics.cost_micros),
    conversions: row.metrics.conversions,
    ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
    avg_cpc: formatMicros(row.metrics.average_cpc),
  }));

  return {
    content: [{ type: "text", text: JSON.stringify({ date_range, report, total: report.length }, null, 2) }],
  };
}

async function keywordPerformance(customer: any, args: any) {
  const { campaign_id, ad_group_id, date_range = "LAST_30_DAYS", order_by = "clicks", limit = 50 } = args || {};

  const orderMap: Record<string, string> = {
    clicks: "metrics.clicks",
    impressions: "metrics.impressions",
    cost: "metrics.cost_micros",
    conversions: "metrics.conversions",
    ctr: "metrics.ctr",
  };

  let query = `
    SELECT
      ad_group_criterion.keyword.text,
      ad_group_criterion.keyword.match_type,
      ad_group.name,
      campaign.name,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.ctr,
      metrics.average_cpc,
      metrics.quality_score
    FROM keyword_view
    WHERE ${getDateClause(date_range)}
  `;

  if (campaign_id) query += ` AND campaign.id = '${campaign_id}'`;
  if (ad_group_id) query += ` AND ad_group.id = '${ad_group_id}'`;
  query += ` ORDER BY ${orderMap[order_by] || "metrics.clicks"} DESC LIMIT ${limit}`;

  const results = await customer.query(query);

  const report = results.map((row: any) => ({
    keyword: row.ad_group_criterion.keyword.text,
    match_type: row.ad_group_criterion.keyword.match_type,
    ad_group: row.ad_group.name,
    campaign: row.campaign.name,
    impressions: row.metrics.impressions,
    clicks: row.metrics.clicks,
    cost: formatMicros(row.metrics.cost_micros),
    conversions: row.metrics.conversions,
    ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
    avg_cpc: formatMicros(row.metrics.average_cpc),
    quality_score: row.metrics.quality_score,
  }));

  return {
    content: [{ type: "text", text: JSON.stringify({ date_range, report, total: report.length }, null, 2) }],
  };
}

async function dailyStats(customer: any, args: any) {
  const { campaign_id, date_range = "LAST_30_DAYS" } = args;

  const query = `
    SELECT
      segments.date,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.ctr
    FROM campaign
    WHERE campaign.id = '${campaign_id}'
      AND ${getDateClause(date_range)}
    ORDER BY segments.date DESC
  `;

  const results = await customer.query(query);

  const dailyData = results.map((row: any) => ({
    date: row.segments.date,
    impressions: row.metrics.impressions,
    clicks: row.metrics.clicks,
    cost: formatMicros(row.metrics.cost_micros),
    conversions: row.metrics.conversions,
    ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
  }));

  return {
    content: [{ type: "text", text: JSON.stringify({ campaign_id, date_range, daily_stats: dailyData }, null, 2) }],
  };
}

async function searchTerms(customer: any, args: any) {
  const { campaign_id, ad_group_id, date_range = "LAST_30_DAYS", limit = 100 } = args || {};

  let query = `
    SELECT
      search_term_view.search_term,
      search_term_view.status,
      campaign.name,
      ad_group.name,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.ctr
    FROM search_term_view
    WHERE ${getDateClause(date_range)}
  `;

  if (campaign_id) query += ` AND campaign.id = '${campaign_id}'`;
  if (ad_group_id) query += ` AND ad_group.id = '${ad_group_id}'`;
  query += ` ORDER BY metrics.clicks DESC LIMIT ${limit}`;

  const results = await customer.query(query);

  const terms = results.map((row: any) => ({
    search_term: row.search_term_view.search_term,
    status: row.search_term_view.status,
    campaign: row.campaign.name,
    ad_group: row.ad_group.name,
    impressions: row.metrics.impressions,
    clicks: row.metrics.clicks,
    cost: formatMicros(row.metrics.cost_micros),
    conversions: row.metrics.conversions,
    ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
  }));

  return {
    content: [{ type: "text", text: JSON.stringify({ date_range, search_terms: terms, total: terms.length }, null, 2) }],
  };
}

async function accountSummary(customer: any, args: any) {
  const { date_range = "LAST_30_DAYS" } = args || {};

  const query = `
    SELECT
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.conversions_value,
      metrics.ctr,
      metrics.average_cpc,
      metrics.cost_per_conversion,
      metrics.interaction_rate
    FROM customer
    WHERE ${getDateClause(date_range)}
  `;

  const results = await customer.query(query);

  if (results.length === 0) {
    return { content: [{ type: "text", text: "No data available for the selected date range" }] };
  }

  const row = results[0];
  const summary = {
    date_range,
    total_impressions: row.metrics.impressions,
    total_clicks: row.metrics.clicks,
    total_cost: formatMicros(row.metrics.cost_micros),
    total_conversions: row.metrics.conversions,
    total_conversion_value: formatMicros(row.metrics.conversions_value || 0),
    avg_ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
    avg_cpc: formatMicros(row.metrics.average_cpc),
    avg_cost_per_conversion: formatMicros(row.metrics.cost_per_conversion || 0),
  };

  return {
    content: [{ type: "text", text: JSON.stringify({ account_summary: summary }, null, 2) }],
  };
}
