import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Promotalk",
  description: "An all-in-one CRM and marketing platform.",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
