import { ModuleStub } from "@/components/module-stub";
import { Workflow } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Workflows"
      description="Visual automation builder (triggers, actions, branching)."
      icon={Workflow}
      features={[
        "Trigger library: form submit, tag added, stage moved, appointment booked, ...",
        "Actions: send SMS / email, add tag, move stage, create task, webhook, AI step",
        "If/else branches and wait steps",
        "Goal-based exits and re-entry rules",
        "Per-contact execution log for debugging",
        "JSON definition stored on the Workflow row for portability",
      ]}
    />
  );
}
