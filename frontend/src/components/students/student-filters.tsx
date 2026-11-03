import { ListFilters } from "@/components/list-filters";
import type { StudentFilters } from "@/lib/student-types";

const statuses = [
  { label: "Active", value: "active" },
  { label: "Archived", value: "archived" },
  { label: "All students", value: "all" },
];

export function StudentFiltersForm({ filters }: { filters: StudentFilters }) {
  return <ListFilters path="/students" filters={{ q: filters.q, status: filters.status }}
    selects={[{ name: "status", label: "Status", defaultValue: "active", options: statuses }]}
    searchLabel="Search students" placeholder="Name, student number, or email" />;
}
