import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import { campaignTools, handleCampaignTool } from "./tools/campaigns.js";
import { adGroupTools, handleAdGroupTool } from "./tools/ad-groups.js";
import { keywordTools, handleKeywordTool } from "./tools/keywords.js";
import { reportTools, handleReportTool } from "./tools/reports.js";
import { budgetTools, handleBudgetTool } from "./tools/budgets.js";
import { accountTools, handleAccountTool } from "./tools/account.js";

const server = new Server(
  {
    name: "google-ads-mcp",
    version: "1.0.0",
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

// Combine all tools
const allTools = [
  ...campaignTools,
  ...adGroupTools,
  ...keywordTools,
  ...reportTools,
  ...budgetTools,
  ...accountTools,
];

// List all available tools
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return { tools: allTools };
});

// Handle tool calls
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    // Route to appropriate handler
    if (name.startsWith("campaign_")) {
      return await handleCampaignTool(name, args);
    }
    if (name.startsWith("adgroup_")) {
      return await handleAdGroupTool(name, args);
    }
    if (name.startsWith("keyword_")) {
      return await handleKeywordTool(name, args);
    }
    if (name.startsWith("report_")) {
      return await handleReportTool(name, args);
    }
    if (name.startsWith("budget_")) {
      return await handleBudgetTool(name, args);
    }
    if (name.startsWith("account_")) {
      return await handleAccountTool(name, args);
    }

    return {
      content: [
        {
          type: "text",
          text: `Unknown tool: ${name}`,
        },
      ],
      isError: true,
    };
  } catch (error: any) {
    return {
      content: [
        {
          type: "text",
          text: `Error: ${error.message}`,
        },
      ],
      isError: true,
    };
  }
});

// Start the server
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("Google Ads MCP Server running on stdio");
}

main().catch((error) => {
  console.error("Fatal error:", error);
  process.exit(1);
});
