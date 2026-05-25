import type { LoaderFunctionArgs } from "@remix-run/node";
import { redirect } from "@remix-run/node";
import { Form, useLoaderData } from "@remix-run/react";
import { login } from "../../shopify.server";

export const loader = async ({ request }: LoaderFunctionArgs) => {
  const url = new URL(request.url);
  if (url.searchParams.get("shop")) {
    throw redirect(`/app?${url.searchParams.toString()}`);
  }
  return { showForm: Boolean(login) };
};

export default function App() {
  const { showForm } = useLoaderData<typeof loader>();
  return (
    <div style={{ fontFamily: "Inter, sans-serif", padding: "2rem", maxWidth: 600, margin: "auto" }}>
      <h1>DataLayer Pro</h1>
      <p>One-click GA4 DataLayer for Shopify with Google Ads remarketing, Facebook, TikTok, Pinterest, LinkedIn, Twitter & full EMQ 10/10.</p>
      {showForm && (
        <Form method="post" action="/auth/login">
          <label>
            <span>Shop domain </span>
            <input type="text" name="shop" placeholder="my-shop.myshopify.com" />
          </label>
          <button type="submit">Install App</button>
        </Form>
      )}
    </div>
  );
}
