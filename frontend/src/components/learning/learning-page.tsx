import { Suspense } from "react";
import { redirect } from "next/navigation";
import { AdminShell } from "@/components/admin-shell";
import { LoadingState } from "@/components/loading-state";
import { LearningTable } from "@/components/learning/learning-list";
import { authenticatedFetch, requirePermission } from "@/lib/auth";
import {
  learningModules,
  type LearningResource,
  type LearningList,
  type LearningOptions,
} from "@/lib/learning-types";
import { schoolToday } from "@/lib/students";
import type { FoundationSearchParams } from "@/components/foundation-page";
async function Content({
  resource,
  searchParams,
}: {
  resource: LearningResource;
  searchParams: FoundationSearchParams;
}) {
  const config = learningModules[resource];
  const user = await requirePermission(config.permission);
  const params = await searchParams;
  const filters: Record<string, string> = {
    q: typeof params.q === "string" ? params.q.slice(0, 100) : "",
  };
  const statuses =
    resource === "admissions"
      ? [
          "draft",
          "submitted",
          "under_review",
          "approved",
          "rejected",
          "withdrawn",
        ]
      : ["active", "completed", "withdrawn", "transferred"];
  if (resource === "admissions" || resource === "enrolments") {
    filters.status = statuses.includes(String(params.status))
      ? String(params.status)
      : "all";
    if (
      typeof params.campus_id === "string" &&
      /^[1-9]\d*$/.test(params.campus_id)
    )
      filters.campus_id = params.campus_id;
  }
  if (
    resource === "enrolments" &&
    typeof params.student_id === "string" &&
    /^[1-9]\d*$/.test(params.student_id)
  )
    filters.student_id = params.student_id;
  const page =
    typeof params.page === "string" &&
    /^[1-9]\d*$/.test(params.page) &&
    Number(params.page) <= 100000
      ? params.page
      : "1";
  const needOptions =
    resource === "admissions" ||
    (resource === "enrolments" &&
      user.permissions.includes("enrolments.manage")) ||
    resource === "links";
  const [response, optionResponse] = await Promise.all([
    authenticatedFetch(
      config.endpoint + "?" + new URLSearchParams({ ...filters, page }),
    ),
    needOptions
      ? authenticatedFetch(
          resource === "admissions"
            ? "/admissions/options"
            : "/learning/options",
        )
      : null,
  ]);
  if (!response.ok || (optionResponse && !optionResponse.ok))
    throw new Error("Unable to load " + config.title.toLowerCase() + ".");
  const result: LearningList = await response.json();
  const options: LearningOptions = optionResponse
    ? (await optionResponse.json()).data
    : {};
  if (Number(page) > result.meta.last_page)
    redirect(
      config.path +
        "?" +
        new URLSearchParams({
          ...filters,
          page: String(result.meta.last_page),
        }),
    );
  return (
    <AdminShell user={user} title={config.title}>
      <LearningTable
        resource={resource}
        result={result}
        filters={filters}
        options={options}
        today={schoolToday()}
      />
    </AdminShell>
  );
}
export function LearningPage(props: {
  resource: LearningResource;
  searchParams: FoundationSearchParams;
}) {
  return (
    <Suspense fallback={<LoadingState message="Loading student workflows…" />}>
      <Content {...props} />
    </Suspense>
  );
}
