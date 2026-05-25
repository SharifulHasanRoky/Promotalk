import { Page, Layout, Card, Text, BlockStack, List } from "@shopify/polaris";

export default function Help() {
  return (
    <Page title="Help" backAction={{ url: "/app" }}>
      <Layout>
        <Layout.Section>
          <Card>
            <BlockStack gap="400">
              <Text as="h2" variant="headingMd">Events Reference</Text>
              <List>
                <List.Item>page_view (every page)</List.Item>
                <List.Item>view_item (product page)</List.Item>
                <List.Item>view_item_list (collection page)</List.Item>
                <List.Item>search</List.Item>
                <List.Item>view_cart</List.Item>
                <List.Item>add_to_cart</List.Item>
                <List.Item>begin_checkout</List.Item>
                <List.Item>purchase</List.Item>
                <List.Item>generate_lead, click_to_call, email_click, chat_click, get_directions</List.Item>
              </List>
            </BlockStack>
          </Card>
        </Layout.Section>
        <Layout.Section>
          <Card>
            <BlockStack gap="400">
              <Text as="h2" variant="headingMd">Google Ads Remarketing Events</Text>
              <List>
                <List.Item>view_item_remarketing</List.Item>
                <List.Item>view_item_list_remarketing</List.Item>
                <List.Item>view_search_results_remarketing</List.Item>
                <List.Item>add_to_cart_remarketing</List.Item>
                <List.Item>purchase_remarketing</List.Item>
              </List>
            </BlockStack>
          </Card>
        </Layout.Section>
      </Layout>
    </Page>
  );
}
