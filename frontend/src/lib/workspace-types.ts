import type { User } from "@/lib/auth";

export type Metric = { key: string; label: string; value: number | null };
export type CampusChoice = { id: number; label: string };
export type Campus = { id: number; name: string; code: string; status: "active" | "inactive" };
export type CampusSummary = Campus & { rooms: number | null; classes: number | null };
export type Activity = { id: number; actor: string; campus_id: number | null; subject_type: string; subject_id: number; action: string; changed_fields: string[]; created_at: string };
export type Overview = { metrics: Metric[]; scope: string; as_of: string };
export type WorkspaceChrome = { overview: Overview | null; campuses: CampusChoice[]; activity: Activity[]; campusError: boolean; activityError: boolean };
export type ClassSummary = { id: number; name: string; code: string; capacity: number; status: string; campus_id: number; labels: Record<string, string | null>; teacher_ids: number[] };
export type WorkspaceDashboard = {
  metrics: Metric[]; asOf: string | null; selectedCampus: Campus | null;
  campuses: CampusSummary[]; campusPage: number; campusLastPage: number; campusTotal: number;
  classes: ClassSummary[]; classesTotal: number | null;
  year: string | null; programmes: number | null; activity: Activity[];
  errors: { overview: boolean; campuses: boolean; classes: boolean; activity: boolean };
};
export type SearchMatch = { id: string; name: string; description: string; href: string; module: string };
export type ShellUser = User;
