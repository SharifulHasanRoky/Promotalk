# DataLayer Pro — Shopify App

Embedded Shopify app with GA4 DataLayer + Google Ads remarketing + Facebook/TikTok/Pinterest/LinkedIn/Twitter parameters + Event Match Quality 10/10.

## Prerequisites

1. [Node.js](https://nodejs.org/) 18+ installed
2. Shopify CLI: `npm install -g @shopify/cli@latest`
3. [Shopify Partner account](https://partners.shopify.com/signup) (free)
4. A development store created in Partners

## Quick Start (5 minutes)

```bash
# 1. Clone repo
git clone https://github.com/SharifulHasanRoky/Promotalk.git
cd Promotalk/shopify-datalayer-app

# 2. Install dependencies
npm install

# 3. Setup database
npx prisma generate
npx prisma migrate dev --name init

# 4. Connect to Shopify Partners (creates app there)
shopify app config link

# 5. Start dev server (auto-installs to your dev store)
npm run dev
```

Shopify CLI will:
- Create the app in your Partners dashboard
- Set up Cloudflare tunnel automatically
- Open install link in browser
- Hot reload on changes

## Deploy to Production

```bash
# Build for production
npm run build

# Deploy theme extension + config to Shopify
shopify app deploy

# Host the Remix app on Vercel/Heroku/Railway/Fly.io
# Set these env vars:
# - SHOPIFY_API_KEY (from Partners)
# - SHOPIFY_API_SECRET (from Partners)
# - SHOPIFY_APP_URL (your hosted URL)
# - SCOPES=read_products,read_orders,read_customers
# - DATABASE_URL (or use sqlite)
```

## Project Structure

```
shopify-datalayer-app/
├── shopify.app.toml          # App config
├── package.json              # Dependencies
├── vite.config.ts            # Build config
├── prisma/schema.prisma      # DB (Session + AppSettings)
├── app/
│   ├── shopify.server.ts     # OAuth + API
│   ├── db.server.ts          # Prisma client
│   ├── root.tsx              # Root layout
│   └── routes/
│       ├── _index/           # Landing page
│       ├── auth.$.tsx        # OAuth handler
│       ├── auth.login/       # Login form
│       ├── app.tsx           # Embedded layout
│       ├── app._index.tsx    # Home dashboard
│       ├── app.settings.tsx  # Settings panel
│       └── app.help.tsx      # Documentation
└── extensions/
    └── datalayer-extension/  # Theme App Extension
        ├── shopify.extension.toml
        ├── blocks/
        │   ├── datalayer_head.liquid
        │   └── datalayer_body.liquid
        └── snippets/
            └── purchase-datalayer.liquid
```

## Features

- GA4 ecommerce schema (view_item, add_to_cart, purchase, etc.)
- Google Ads dynamic remarketing (updated event names)
- Multi-platform parameters (FB/TT/Pin/LI/TW in single event)
- Event Match Quality 10/10
- Cookie management (_fbp, _fbc, gclid, external_id)
- Local service events
- Customer lifecycle tracking
- Embedded admin UI with Polaris
- Theme App Extension (one-click activation)

## License

MIT
