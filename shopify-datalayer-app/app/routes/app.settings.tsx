import type { ActionFunctionArgs, LoaderFunctionArgs } from "@remix-run/node";
import { useLoaderData, Form, useActionData } from "@remix-run/react";
import { Page, Layout, Card, Text, BlockStack, FormLayout, Select, Checkbox, Button, Banner, TextField } from "@shopify/polaris";
import { authenticate } from "../shopify.server";
import prisma from "../db.server";

export const loader = async ({ request }: LoaderFunctionArgs) => {
  const { session } = await authenticate.admin(request);
  let settings = await prisma.appSettings.findUnique({ where: { shop: session.shop } });
  if (!settings) settings = await prisma.appSettings.create({ data: { shop: session.shop } });
  return { settings };
};

export const action = async ({ request }: ActionFunctionArgs) => {
  const { session } = await authenticate.admin(request);
  const fd = await request.formData();
  const updated = await prisma.appSettings.update({
    where: { shop: session.shop },
    data: {
      enabled: fd.get("enabled") === "on",
      businessVertical: String(fd.get("businessVertical") || "retail"),
      cookieDuration: Number(fd.get("cookieDuration") || 390),
      trackEcommerce: fd.get("trackEcommerce") === "on",
      trackLocalService: fd.get("trackLocalService") === "on",
      trackForms: fd.get("trackForms") === "on",
      trackScrollDepth: fd.get("trackScrollDepth") === "on",
      trackRemarketing: fd.get("trackRemarketing") === "on",
      emqEnabled: fd.get("emqEnabled") === "on",
    },
  });
  return { success: true, settings: updated };
};

export default function Settings() {
  const { settings } = useLoaderData<typeof loader>();
  const actionData = useActionData<typeof action>();

  return (
    <Page title="Settings" backAction={{ url: "/app" }}>
      <Layout>
        {actionData?.success && (
          <Layout.Section>
            <Banner tone="success">Settings saved!</Banner>
          </Layout.Section>
        )}
        <Layout.Section>
          <Card>
            <Form method="post">
              <FormLayout>
                <Checkbox label="Enable DataLayer" name="enabled" checked={settings.enabled} />
                <Select
                  label="Business Vertical"
                  name="businessVertical"
                  options={[
                    { label: "Retail / Ecommerce", value: "retail" },
                    { label: "Education", value: "education" },
                    { label: "Hotels & Rentals", value: "hotels_rentals" },
                    { label: "Local Services", value: "local" },
                    { label: "Real Estate", value: "real_estate" },
                    { label: "Travel", value: "travel" },
                  ]}
                  value={settings.businessVertical}
                />
                <TextField label="Cookie Duration (days)" name="cookieDuration" type="number" value={String(settings.cookieDuration)} autoComplete="off" />

                <Text as="h3" variant="headingSm">Event Tracking</Text>
                <Checkbox label="Ecommerce events" name="trackEcommerce" checked={settings.trackEcommerce} />
                <Checkbox label="Local service events" name="trackLocalService" checked={settings.trackLocalService} />
                <Checkbox label="Form submissions" name="trackForms" checked={settings.trackForms} />
                <Checkbox label="Scroll depth" name="trackScrollDepth" checked={settings.trackScrollDepth} />
                <Checkbox label="Google Ads remarketing" name="trackRemarketing" checked={settings.trackRemarketing} />

                <Text as="h3" variant="headingSm">Event Match Quality</Text>
                <Checkbox label="EMQ 10/10" name="emqEnabled" checked={settings.emqEnabled} />

                <Button submit variant="primary">Save Settings</Button>
              </FormLayout>
            </Form>
          </Card>
        </Layout.Section>
      </Layout>
    </Page>
  );
}
