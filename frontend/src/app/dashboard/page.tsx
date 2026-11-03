import { Suspense } from "react";
import { AdminShell } from "@/components/admin-shell";
import { DashboardView } from "@/components/dashboard/dashboard-view";
import { LoadingState } from "@/components/loading-state";
import { requireUser } from "@/lib/auth";
import { getWorkspaceDashboard } from "@/lib/workspace-data";
import type { FoundationSearchParams } from "@/components/foundation-page";

async function Dashboard({ searchParams }: { searchParams: FoundationSearchParams }) {
  const user = await requireUser();
  const params = await searchParams;
  const campusId = typeof params.campus_id === "string" ? params.campus_id : "";
  const data = await getWorkspaceDashboard(user, campusId);
  return <AdminShell user={user} title="Dashboard Overview"><DashboardView data={data} /></AdminShell>;
}

export default function DashboardPage(props: { searchParams: FoundationSearchParams }) {
  return <Suspense fallback={<LoadingState message="Loading your dashboard…" />}><Dashboard {...props} /></Suspense>;
}
