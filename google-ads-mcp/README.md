# Google Ads MCP Server

A full-featured Model Context Protocol (MCP) server for Google Ads API. Manage campaigns, ad groups, keywords, budgets, and view performance reports — all through AI assistants like Claude.

## Features

| Module | Tools |
|--------|-------|
| **Campaigns** | List, Get, Create, Update, Pause, Enable, Delete |
| **Ad Groups** | List, Get, Create, Update, Pause, Enable |
| **Keywords** | List, Add, Remove, Update Bid, Search Volume, Negative Keywords |
| **Reports** | Campaign Performance, Ad Group Performance, Keyword Performance, Daily Stats, Search Terms, Account Summary |
| **Budgets** | List, Get, Create, Update, Spending Summary |
| **Account** | Account Info, Account Hierarchy (MCC) |

## Prerequisites

1. **Google Ads API Access** — You need an approved [Google Ads Developer Token](https://developers.google.com/google-ads/api/docs/get-started/dev-token)
2. **OAuth2 Credentials** — A Google Cloud project with OAuth2 client ID and secret
3. **Refresh Token** — An OAuth2 refresh token with Google Ads API scope
4. **Customer ID** — Your Google Ads account customer ID (without dashes)

## Setup

### 1. Install Dependencies

```bash
cd google-ads-mcp
npm install
```

### 2. Build

```bash
npm run build
```

### 3. Configure Environment Variables

Create a `.env` file or set these environment variables:

```env
GOOGLE_ADS_CLIENT_ID=your-oauth2-client-id.apps.googleusercontent.com
GOOGLE_ADS_CLIENT_SECRET=your-oauth2-client-secret
GOOGLE_ADS_DEVELOPER_TOKEN=your-developer-token
GOOGLE_ADS_REFRESH_TOKEN=your-oauth2-refresh-token
GOOGLE_ADS_CUSTOMER_ID=1234567890
GOOGLE_ADS_LOGIN_CUSTOMER_ID=9876543210  # Optional: for MCC accounts
```

### 4. Getting Your Credentials

#### Developer Token
1. Sign in to your Google Ads account
2. Go to **Tools & Settings → API Center**
3. Apply for a developer token (starts with Basic access)

#### OAuth2 Client ID & Secret
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a project (or use existing)
3. Enable **Google Ads API**
4. Go to **Credentials → Create Credentials → OAuth 2.0 Client ID**
5. Choose "Desktop App" type
6. Copy the Client ID and Client Secret

#### Refresh Token
Use the OAuth2 playground or run this script:

```bash
# Install google helper
npm install -g google-auth-library

# Generate auth URL, visit it, and exchange code for tokens
# Scope needed: https://www.googleapis.com/auth/adwords
```

Or use the [OAuth2 Playground](https://developers.google.com/oauthplayground/):
1. Select "Google Ads API v17" scope
2. Authorize with your Google account
3. Exchange authorization code for tokens
4. Copy the refresh token

## Usage with Claude Desktop

Add to your Claude Desktop config (`claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "google-ads": {
      "command": "node",
      "args": ["/path/to/google-ads-mcp/dist/index.js"],
      "env": {
        "GOOGLE_ADS_CLIENT_ID": "your-client-id",
        "GOOGLE_ADS_CLIENT_SECRET": "your-client-secret",
        "GOOGLE_ADS_DEVELOPER_TOKEN": "your-dev-token",
        "GOOGLE_ADS_REFRESH_TOKEN": "your-refresh-token",
        "GOOGLE_ADS_CUSTOMER_ID": "1234567890"
      }
    }
  }
}
```

## Available Tools

### Campaign Management
- `campaign_list` — List all campaigns with metrics
- `campaign_get` — Get detailed campaign info
- `campaign_create` — Create new campaign with budget & bidding strategy
- `campaign_update` — Update campaign settings
- `campaign_pause` — Pause a campaign
- `campaign_enable` — Enable a paused campaign
- `campaign_delete` — Remove a campaign

### Ad Group Management
- `adgroup_list` — List ad groups (filter by campaign)
- `adgroup_get` — Get ad group details
- `adgroup_create` — Create new ad group
- `adgroup_update` — Update ad group settings
- `adgroup_pause` — Pause an ad group
- `adgroup_enable` — Enable a paused ad group

### Keyword Management
- `keyword_list` — List keywords with metrics
- `keyword_add` — Add keywords to an ad group
- `keyword_remove` — Remove keywords
- `keyword_update_bid` — Update keyword CPC bid
- `keyword_get_search_volume` — Research keyword search volume
- `keyword_get_negative` — List negative keywords
- `keyword_add_negative` — Add negative keywords

### Performance Reports
- `report_campaign_performance` — Campaign metrics by date range
- `report_ad_group_performance` — Ad group metrics
- `report_keyword_performance` — Keyword metrics with quality scores
- `report_daily_stats` — Day-by-day breakdown
- `report_search_terms` — Actual search queries that triggered ads
- `report_account_summary` — High-level account overview

### Budget Management
- `budget_list` — List all budgets
- `budget_get` — Get budget details
- `budget_create` — Create new budget
- `budget_update` — Update budget amount
- `budget_spending_summary` — Spending vs budget analysis

### Account
- `account_info` — Account details (currency, timezone, etc.)
- `account_hierarchy` — MCC account hierarchy

## Example Prompts

Once connected, you can ask Claude things like:

- "Show me all my active campaigns and their performance"
- "Create a new Search campaign with $50/day budget targeting maximize clicks"
- "What are my top performing keywords this month?"
- "Pause campaign 12345"
- "Add these keywords to ad group 67890: running shoes, best sneakers, athletic footwear"
- "Show me the search terms report for last 7 days"
- "What's my total ad spend this month?"
- "Research search volume for: digital marketing, SEO services, online advertising"

## Development

```bash
# Run in development mode
npm run dev

# Build for production
npm run build

# Start production server
npm start
```

## License

MIT
