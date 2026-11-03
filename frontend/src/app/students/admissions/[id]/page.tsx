import { Suspense } from "react";
import { notFound } from "next/navigation";
import { AdminShell } from "@/components/admin-shell";
import { LoadingState } from "@/components/loading-state";
import { AdmissionDetail } from "@/components/learning/admission-detail";
import { authenticatedFetch, requirePermission } from "@/lib/auth";
async function Content({ params }: { params: Promise<{ id: string }> }) {
  const user = await requirePermission("admissions.view");
  const { id } = await params;
  if (!/^[1-9]\d{0,15}$/.test(id)) notFound();
  const [response, options] = await Promise.all([
    authenticatedFetch("/admissions/" + id),
    authenticatedFetch("/admissions/options"),
  ]);
  if (response.status === 404) notFound();
  if (!response.ok || !options.ok)
    throw new Error("Unable to load the application.");
  return (
    <AdminShell user={user} title="Admissions">
      <AdmissionDetail
        application={(await response.json()).data}
        options={(await options.json()).data}
      />
    </AdminShell>
  );
}
export default function Page(props: { params: Promise<{ id: string }> }) {
  return (
    <Suspense fallback={<LoadingState message="Loading admission…" />}>
      <Content {...props} />
    </Suspense>
  );
}
