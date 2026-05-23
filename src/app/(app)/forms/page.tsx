import { ModuleStub } from "@/components/module-stub";
import { ClipboardList } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Forms & Surveys"
      description="Capture leads and collect data."
      icon={ClipboardList}
      features={[
        "Drag-and-drop field builder (text, select, file, signature, ...)",
        "Conditional fields and multi-step forms",
        "Embed code, hosted page, and popup variants",
        "Auto-create / update contacts on submit",
        "Workflow trigger: 'Form submitted'",
        "Submission table with CSV export",
      ]}
    />
  );
}
