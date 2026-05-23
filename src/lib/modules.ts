/**
 * Single source of truth for the side-nav modules.
 * Every entry here automatically shows up in the sidebar.
 */
import {
  LayoutDashboard,
  Users,
  MessageSquare,
  Calendar,
  KanbanSquare,
  Megaphone,
  Workflow,
  ClipboardList,
  Globe,
  GraduationCap,
  Star,
  CreditCard,
  BarChart3,
  Settings,
  type LucideIcon,
} from "lucide-react";

export type AppModule = {
  href: string;
  label: string;
  icon: LucideIcon;
  description: string;
};

export const modules: AppModule[] = [
  { href: "/dashboard",     label: "Dashboard",      icon: LayoutDashboard, description: "At-a-glance KPIs across every module." },
  { href: "/contacts",      label: "Contacts",       icon: Users,           description: "Your CRM database. People, companies, tags, custom fields." },
  { href: "/conversations", label: "Conversations",  icon: MessageSquare,   description: "Unified inbox: SMS, email, voice, webchat, social DMs." },
  { href: "/calendars",     label: "Calendars",      icon: Calendar,        description: "Booking pages and appointment scheduling." },
  { href: "/pipelines",     label: "Pipelines",      icon: KanbanSquare,    description: "Sales pipelines, stages, and opportunities." },
  { href: "/campaigns",     label: "Campaigns",      icon: Megaphone,       description: "Outbound SMS / email blasts and drips." },
  { href: "/workflows",     label: "Workflows",      icon: Workflow,        description: "Visual automation builder (triggers, actions, branching)." },
  { href: "/forms",         label: "Forms & Surveys",icon: ClipboardList,   description: "Capture leads and collect data." },
  { href: "/sites",         label: "Sites & Funnels",icon: Globe,           description: "Hosted landing pages and funnels." },
  { href: "/memberships",   label: "Memberships",    icon: GraduationCap,   description: "Courses, communities, and gated content." },
  { href: "/reputation",    label: "Reputation",     icon: Star,            description: "Review requests and rating monitoring." },
  { href: "/payments",      label: "Payments",       icon: CreditCard,      description: "Products, invoices, and Stripe-style checkouts." },
  { href: "/reporting",     label: "Reporting",      icon: BarChart3,       description: "Cross-module analytics and attribution." },
  { href: "/settings",      label: "Settings",       icon: Settings,        description: "Sub-accounts, team, integrations, billing." },
];
