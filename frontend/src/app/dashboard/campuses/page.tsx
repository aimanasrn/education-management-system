import { Suspense } from "react";
import { redirect } from "next/navigation";
import { AdminShell } from "@/components/admin-shell";
import { DashboardView } from "@/components/dashboard/dashboard-view";
import { LoadingState } from "@/components/loading-state";
import { requirePermission } from "@/lib/auth";
import { getWorkspaceDashboard } from "@/lib/workspace-data";
import type { FoundationSearchParams } from "@/components/foundation-page";

async function Monitor({ searchParams }: { searchParams: FoundationSearchParams }) {
  const user = await requirePermission("campuses.view");
  const params = await searchParams;
  const campusId = typeof params.campus_id === "string" ? params.campus_id : "";
  const page = typeof params.page === "string" && /^[1-9]\d*$/.test(params.page) && Number(params.page) <= 100000 ? Number(params.page) : 1;
  const data = await getWorkspaceDashboard(user, campusId, page);
  if (page > data.campusLastPage) redirect("/dashboard/campuses?" + new URLSearchParams({ ...(campusId ? {campus_id:campusId} : {}), page:String(data.campusLastPage) }));
  return <AdminShell user={user} title="Campus Live Monitor"><DashboardView data={data} monitor /></AdminShell>;
}

export default function CampusMonitorPage(props: { searchParams: FoundationSearchParams }) {
  return <Suspense fallback={<LoadingState message="Loading campus monitor…" />}><Monitor {...props} /></Suspense>;
}
