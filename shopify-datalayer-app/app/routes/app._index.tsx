import type { LoaderFunctionArgs } from "@remix-run/node";
import { useLoaderData } from "@remix-run/react";
import { Page, Layout, Card, Text, BlockStack, Banner, List, Button, InlineStack, Badge } from "@shopify/polaris";
import { authenticate } from "../shopify.server";
import prisma from "../db.server";

export const loader = async ({ request }: LoaderFunctionArgs) => {
  const { session } = await authenticate.admin(request);
  let settings = await prisma.appSettings.findUnique({ where: { shop: session.shop } });
  if (!settings) settings = await prisma.appSettings.create({ data: { shop: session.shop } });
  return { shop: session.shop, settings };
};

export default function Index() {
  const { shop, settings } = useLoaderData<typeof loader>();
  return (
    <Page title="DataLayer Pro" subtitle="GA4 DataLayer with all platforms support">
      <Layout>
        <Layout.Section>
          <Banner title="App installed successfully!" tone="success">
            <p>Now activate the theme app extension to start tracking events.</p>
          </Banner>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="400">
              <Text as="h2" variant="headingMd">Status</Text>
              <InlineStack gap="200">
                <Badge tone={settings.enabled ? "success" : "critical"}>
                  {settings.enabled ? "Enabled" : "Disabled"}
                </Badge>
                <Badge tone="info">{settings.businessVertical}</Badge>
                <Badge>{`Cookie: ${settings.cookieDuration} days`}</Badge>
              </InlineStack>
              <Text as="p">Shop: {shop}</Text>
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="400">
              <Text as="h2" variant="headingMd">Quick Setup (3 steps)</Text>
              <List type="number">
                <List.Item>Online Store -- Themes -- Customize</List.Item>
                <List.Item>Click App embeds in left sidebar</List.Item>
                <List.Item>Toggle DataLayer Pro ON -- Save</List.Item>
              </List>
              <Button url={`https://${shop}/admin/themes/current/editor`} target="_blank" variant="primary">
                Open Theme Customizer
              </Button>
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="400">
              <Text as="h2" variant="headingMd">Features</Text>
              <List>
                <List.Item>GA4 ecommerce schema (view_item, add_to_cart, purchase)</List.Item>
                <List.Item>Google Ads dynamic remarketing (updated events)</List.Item>
                <List.Item>Facebook, TikTok, Pinterest, LinkedIn, Twitter parameters</List.Item>
                <List.Item>Event Match Quality 10/10 with all user data</List.Item>
                <List.Item>Cookie management (_fbp, _fbc, gclid, external_id)</List.Item>
                <List.Item>Local service events (call, email, chat, directions)</List.Item>
              </List>
            </BlockStack>
          </Card>
        </Layout.Section>
      </Layout>
    </Page>
  );
}
