# Shopify DataLayer — সরাসরি Install Guide

কোনো app install করতে হবে না! শুধু **৩টা code paste** করো Shopify admin এ।

---

## 📋 Step 1: Theme তে Main DataLayer Code Add করো

**Shopify Admin → Online Store → Themes → Edit Code**

`layout/theme.liquid` ফাইল খোলো → `<head>` ট্যাগের মধ্যে নিচের কোডটা paste করো (GTM/GA tag এর আগে):

👉 কোড: `theme-head.liquid` ফাইল থেকে copy করো

---

## 📋 Step 2: Checkout Purchase Tracking Add করো

**Shopify Admin → Settings → Checkout → Order status page → Additional scripts**

👉 কোড: `checkout-additional-scripts.liquid` ফাইল থেকে copy করো

---

## 📋 Step 3: Customer Events Pixel Add করো (Recommended)

**Shopify Admin → Settings → Customer events → Add custom pixel**

- Name: `DataLayer Pro`
- Permission: **Not required** (we don't track PII directly)
- Events: paste from `customer-events-pixel.js`

---

## ✅ Done!

GTM দিয়ে এখন সব events catch করতে পারবে।
