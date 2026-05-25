import { getCustomer, formatMicros, toMicros } from "../utils/google-ads-client.js";

export const keywordTools = [
  {
    name: "keyword_list",
    description: "List keywords for an ad group or campaign",
    inputSchema: {
      type: "object" as const,
      properties: {
        ad_group_id: { type: "string", description: "Filter by ad group ID" },
        campaign_id: { type: "string", description: "Filter by campaign ID" },
        status: { type: "string", enum: ["ENABLED", "PAUSED", "REMOVED", "ALL"], description: "Filter by status. Default: ALL" },
        limit: { type: "number", description: "Max results. Default: 100" },
      },
    },
  },
  {
    name: "keyword_add",
    description: "Add keywords to an ad group",
    inputSchema: {
      type: "object" as const,
      properties: {
        ad_group_id: { type: "string", description: "Ad group ID to add keywords to" },
        keywords: {
          type: "array",
          items: {
            type: "object",
            properties: {
              text: { type: "string", description: "Keyword text" },
              match_type: { type: "string", enum: ["EXACT", "PHRASE", "BROAD"], description: "Match type. Default: BROAD" },
              cpc_bid: { type: "number", description: "Optional CPC bid override" },
            },
            required: ["text"],
          },
          description: "Array of keywords to add",
        },
      },
      required: ["ad_group_id", "keywords"],
    },
  },
  {
    name: "keyword_remove",
    description: "Remove (pause) keywords from an ad group",
    inputSchema: {
      type: "object" as const,
      properties: {
        criterion_ids: {
          type: "array",
          items: { type: "string" },
          description: "Array of keyword criterion IDs to remove",
        },
        ad_group_id: { type: "string", description: "Ad group ID containing the keywords" },
      },
      required: ["criterion_ids", "ad_group_id"],
    },
  },
  {
    name: "keyword_update_bid",
    description: "Update the CPC bid for a keyword",
    inputSchema: {
      type: "object" as const,
      properties: {
        ad_group_id: { type: "string", description: "Ad group ID" },
        criterion_id: { type: "string", description: "Keyword criterion ID" },
        cpc_bid: { type: "number", description: "New CPC bid amount" },
      },
      required: ["ad_group_id", "criterion_id", "cpc_bid"],
    },
  },
  {
    name: "keyword_get_search_volume",
    description: "Get keyword ideas and search volume estimates using the Keyword Plan",
    inputSchema: {
      type: "object" as const,
      properties: {
        keywords: {
          type: "array",
          items: { type: "string" },
          description: "Keywords to research",
        },
        language_id: { type: "string", description: "Language criterion ID. Default: 1000 (English)" },
        geo_target_id: { type: "string", description: "Geo target criterion ID. Default: 2840 (US)" },
      },
      required: ["keywords"],
    },
  },
  {
    name: "keyword_get_negative",
    description: "List negative keywords for a campaign",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: { type: "string", description: "Campaign ID" },
      },
      required: ["campaign_id"],
    },
  },
  {
    name: "keyword_add_negative",
    description: "Add negative keywords to a campaign",
    inputSchema: {
      type: "object" as const,
      properties: {
        campaign_id: { type: "string", description: "Campaign ID" },
        keywords: {
          type: "array",
          items: {
            type: "object",
            properties: {
              text: { type: "string" },
              match_type: { type: "string", enum: ["EXACT", "PHRASE", "BROAD"] },
            },
            required: ["text"],
          },
          description: "Negative keywords to add",
        },
      },
      required: ["campaign_id", "keywords"],
    },
  },
];

export async function handleKeywordTool(name: string, args: any) {
  const customer = getCustomer();

  switch (name) {
    case "keyword_list": return await listKeywords(customer, args);
    case "keyword_add": return await addKeywords(customer, args);
    case "keyword_remove": return await removeKeywords(customer, args);
    case "keyword_update_bid": return await updateKeywordBid(customer, args);
    case "keyword_get_search_volume": return await getSearchVolume(customer, args);
    case "keyword_get_negative": return await getNegativeKeywords(customer, args);
    case "keyword_add_negative": return await addNegativeKeywords(customer, args);
    default:
      return { content: [{ type: "text", text: `Unknown keyword tool: ${name}` }], isError: true };
  }
}

async function listKeywords(customer: any, args: any) {
  const { ad_group_id, campaign_id, status = "ALL", limit = 100 } = args || {};

  let query = `
    SELECT
      ad_group_criterion.criterion_id,
      ad_group_criterion.keyword.text,
      ad_group_criterion.keyword.match_type,
      ad_group_criterion.status,
      ad_group_criterion.cpc_bid_micros,
      ad_group.id,
      ad_group.name,
      campaign.id,
      metrics.impressions,
      metrics.clicks,
      metrics.cost_micros,
      metrics.conversions,
      metrics.ctr,
      metrics.quality_score
    FROM keyword_view
  `;

  const conditions: string[] = [];
  if (ad_group_id) conditions.push(`ad_group.id = '${ad_group_id}'`);
  if (campaign_id) conditions.push(`campaign.id = '${campaign_id}'`);
  if (status !== "ALL") conditions.push(`ad_group_criterion.status = '${status}'`);

  if (conditions.length > 0) query += ` WHERE ${conditions.join(" AND ")}`;
  query += ` LIMIT ${limit}`;

  const results = await customer.query(query);

  const keywords = results.map((row: any) => ({
    criterion_id: row.ad_group_criterion.criterion_id,
    text: row.ad_group_criterion.keyword.text,
    match_type: row.ad_group_criterion.keyword.match_type,
    status: row.ad_group_criterion.status,
    cpc_bid: formatMicros(row.ad_group_criterion.cpc_bid_micros || 0),
    ad_group: { id: row.ad_group.id, name: row.ad_group.name },
    metrics: {
      impressions: row.metrics.impressions,
      clicks: row.metrics.clicks,
      cost: formatMicros(row.metrics.cost_micros),
      conversions: row.metrics.conversions,
      ctr: `${(row.metrics.ctr * 100).toFixed(2)}%`,
      quality_score: row.metrics.quality_score,
    },
  }));

  return {
    content: [{ type: "text", text: JSON.stringify({ keywords, total: keywords.length }, null, 2) }],
  };
}

async function addKeywords(customer: any, args: any) {
  const { ad_group_id, keywords } = args;

  const operations = keywords.map((kw: any) => {
    const data: any = {
      ad_group: `customers/${customer.credentials.customer_id}/adGroups/${ad_group_id}`,
      keyword: {
        text: kw.text,
        match_type: kw.match_type || "BROAD",
      },
      status: "ENABLED",
    };
    if (kw.cpc_bid) data.cpc_bid_micros = toMicros(kw.cpc_bid);
    return data;
  });

  const result = await customer.adGroupCriteria.create(operations);

  return {
    content: [{
      type: "text",
      text: JSON.stringify({
        success: true,
        message: `${keywords.length} keyword(s) added to ad group ${ad_group_id}`,
        keywords_added: keywords.map((kw: any) => kw.text),
      }, null, 2),
    }],
  };
}

async function removeKeywords(customer: any, args: any) {
  const { criterion_ids, ad_group_id } = args;

  const operations = criterion_ids.map((id: string) => ({
    resource_name: `customers/${customer.credentials.customer_id}/adGroupCriteria/${ad_group_id}~${id}`,
    status: 4, // REMOVED
  }));

  await customer.adGroupCriteria.update(operations);

  return {
    content: [{
      type: "text",
      text: JSON.stringify({
        success: true,
        message: `${criterion_ids.length} keyword(s) removed`,
      }, null, 2),
    }],
  };
}

async function updateKeywordBid(customer: any, args: any) {
  const { ad_group_id, criterion_id, cpc_bid } = args;

  await customer.adGroupCriteria.update([{
    resource_name: `customers/${customer.credentials.customer_id}/adGroupCriteria/${ad_group_id}~${criterion_id}`,
    cpc_bid_micros: toMicros(cpc_bid),
  }]);

  return {
    content: [{
      type: "text",
      text: JSON.stringify({
        success: true,
        message: `Keyword ${criterion_id} bid updated to ${cpc_bid}`,
      }, null, 2),
    }],
  };
}

async function getSearchVolume(customer: any, args: any) {
  const { keywords, language_id = "1000", geo_target_id = "2840" } = args;

  const result = await customer.keywordPlanIdeas.generateKeywordIdeas({
    keyword_seed: { keywords },
    language: `languageConstants/${language_id}`,
    geo_target_constants: [`geoTargetConstants/${geo_target_id}`],
    keyword_plan_network: "GOOGLE_SEARCH",
  });

  const ideas = result.results?.map((idea: any) => ({
    keyword: idea.text,
    avg_monthly_searches: idea.keyword_idea_metrics?.avg_monthly_searches,
    competition: idea.keyword_idea_metrics?.competition,
    low_bid: formatMicros(idea.keyword_idea_metrics?.low_top_of_page_bid_micros || 0),
    high_bid: formatMicros(idea.keyword_idea_metrics?.high_top_of_page_bid_micros || 0),
  })) || [];

  return {
    content: [{ type: "text", text: JSON.stringify({ keyword_ideas: ideas, total: ideas.length }, null, 2) }],
  };
}

async function getNegativeKeywords(customer: any, args: any) {
  const { campaign_id } = args;

  const query = `
    SELECT
      campaign_criterion.criterion_id,
      campaign_criterion.keyword.text,
      campaign_criterion.keyword.match_type,
      campaign_criterion.negative
    FROM campaign_criterion
    WHERE campaign.id = '${campaign_id}'
      AND campaign_criterion.negative = TRUE
      AND campaign_criterion.type = 'KEYWORD'
  `;

  const results = await customer.query(query);

  const negativeKeywords = results.map((row: any) => ({
    criterion_id: row.campaign_criterion.criterion_id,
    text: row.campaign_criterion.keyword.text,
    match_type: row.campaign_criterion.keyword.match_type,
  }));

  return {
    content: [{ type: "text", text: JSON.stringify({ negative_keywords: negativeKeywords, total: negativeKeywords.length }, null, 2) }],
  };
}

async function addNegativeKeywords(customer: any, args: any) {
  const { campaign_id, keywords } = args;

  const operations = keywords.map((kw: any) => ({
    campaign: `customers/${customer.credentials.customer_id}/campaigns/${campaign_id}`,
    keyword: {
      text: kw.text,
      match_type: kw.match_type || "BROAD",
    },
    negative: true,
  }));

  await customer.campaignCriteria.create(operations);

  return {
    content: [{
      type: "text",
      text: JSON.stringify({
        success: true,
        message: `${keywords.length} negative keyword(s) added to campaign ${campaign_id}`,
        keywords_added: keywords.map((kw: any) => kw.text),
      }, null, 2),
    }],
  };
}
