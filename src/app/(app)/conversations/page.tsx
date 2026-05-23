import { ModuleStub } from "@/components/module-stub";
import { MessageSquare } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Conversations"
      description="Unified inbox: SMS, email, voice, webchat, and social DMs."
      icon={MessageSquare}
      features={[
        "Two-pane inbox (threads + conversation view)",
        "SMS via Twilio, email via Resend / SES",
        "Voice calls + recordings + transcripts",
        "Webchat widget for sites",
        "Facebook Messenger / Instagram DMs / WhatsApp",
        "Templates, snippets, and AI reply suggestions",
        "Assignment, internal notes, read receipts",
      ]}
    />
  );
}
