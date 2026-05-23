import { GoogleAdsApi, Customer } from "google-ads-api";

export interface GoogleAdsConfig {
  clientId: string;
  clientSecret: string;
  developerToken: string;
  refreshToken: string;
  customerId: string;
  loginCustomerId?: string;
}

let clientInstance: GoogleAdsApi | null = null;
let customerInstance: Customer | null = null;

function getConfig(): GoogleAdsConfig {
  const config: GoogleAdsConfig = {
    clientId: process.env.GOOGLE_ADS_CLIENT_ID || "",
    clientSecret: process.env.GOOGLE_ADS_CLIENT_SECRET || "",
    developerToken: process.env.GOOGLE_ADS_DEVELOPER_TOKEN || "",
    refreshToken: process.env.GOOGLE_ADS_REFRESH_TOKEN || "",
    customerId: process.env.GOOGLE_ADS_CUSTOMER_ID || "",
    loginCustomerId: process.env.GOOGLE_ADS_LOGIN_CUSTOMER_ID || undefined,
  };

  if (!config.clientId || !config.clientSecret || !config.developerToken || !config.refreshToken || !config.customerId) {
    throw new Error(
      "Missing required Google Ads API credentials. Please set environment variables: " +
      "GOOGLE_ADS_CLIENT_ID, GOOGLE_ADS_CLIENT_SECRET, GOOGLE_ADS_DEVELOPER_TOKEN, " +
      "GOOGLE_ADS_REFRESH_TOKEN, GOOGLE_ADS_CUSTOMER_ID"
    );
  }

  return config;
}

export function getClient(): GoogleAdsApi {
  if (!clientInstance) {
    const config = getConfig();
    clientInstance = new GoogleAdsApi({
      client_id: config.clientId,
      client_secret: config.clientSecret,
      developer_token: config.developerToken,
    });
  }
  return clientInstance;
}

export function getCustomer(): Customer {
  if (!customerInstance) {
    const config = getConfig();
    const client = getClient();
    customerInstance = client.Customer({
      customer_id: config.customerId,
      refresh_token: config.refreshToken,
      login_customer_id: config.loginCustomerId,
    });
  }
  return customerInstance;
}

export function formatMicros(micros: number): string {
  return (micros / 1_000_000).toFixed(2);
}

export function toMicros(amount: number): number {
  return Math.round(amount * 1_000_000);
}
