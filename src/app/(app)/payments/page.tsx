import { ModuleStub } from "@/components/module-stub";
import { CreditCard } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Payments"
      description="Products, invoices, and Stripe-style checkouts."
      icon={CreditCard}
      features={[
        "Products and subscription plans",
        "Hosted invoices with payment links",
        "One-time and recurring checkouts",
        "Stripe Connect for sub-account payouts",
        "Coupons, taxes, and trials",
        "Workflow triggers on payment success / failure",
      ]}
    />
  );
}
