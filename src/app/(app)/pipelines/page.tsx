import { ModuleStub } from "@/components/module-stub";
import { KanbanSquare } from "lucide-react";

export default function Page() {
  return (
    <ModuleStub
      title="Pipelines"
      description="Sales pipelines, stages, and opportunities."
      icon={KanbanSquare}
      features={[
        "Drag-and-drop kanban board",
        "Multiple pipelines per sub-account",
        "Custom stages with colors and probabilities",
        "Opportunity value, owner, source, and tags",
        "Forecasting and stage conversion analytics",
        "Bulk move, bulk update, automations on stage change",
      ]}
    />
  );
}
