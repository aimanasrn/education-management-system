import { Suspense } from "react";
import { redirect } from "next/navigation";
import { AdminShell } from "@/components/admin-shell";
import { LoadingState } from "@/components/loading-state";
import { BookingList } from "@/components/bookings/booking-list";
import { authenticatedFetch, requirePermission } from "@/lib/auth";
import { schoolToday } from "@/lib/students";
import type { FoundationSearchParams } from "@/components/foundation-page";
async function Content({
  searchParams,
}: {
  searchParams: FoundationSearchParams;
}) {
  const user = await requirePermission("bookings.view");
  const p = await searchParams;
  const filters: Record<string, string> = {
    q: typeof p.q === "string" ? p.q.slice(0, 100) : "",
    status: [
      "requested",
      "confirmed",
      "declined",
      "cancelled",
      "completed",
    ].includes(String(p.status))
      ? String(p.status)
      : "all",
    booking_type: ["booking", "event", "maintenance"].includes(
      String(p.booking_type),
    )
      ? String(p.booking_type)
      : "all",
    period: ["week", "nextweek", "all"].includes(String(p.period))
      ? String(p.period)
      : "next30",
  };
  for (const k of ["campus_id", "room_id"]) {
    if (typeof p[k] === "string" && /^[1-9]\d{0,15}$/.test(p[k]))
      filters[k] = p[k];
  }
  const page =
    typeof p.page === "string" &&
    /^[1-9]\d*$/.test(p.page) &&
    Number(p.page) <= 100000
      ? p.page
      : "1";
  const query = new URLSearchParams({ ...filters, page });
  query.delete("period");
  const today = schoolToday();
  const date = new Date(today + "T00:00:00Z");
  if (filters.period !== "all") {
    if (filters.period === "week" || filters.period === "nextweek") {
      date.setUTCDate(
        date.getUTCDate() -
          ((date.getUTCDay() + 6) % 7) +
          (filters.period === "nextweek" ? 7 : 0),
      );
      query.set("from", date.toISOString().slice(0, 10));
      date.setUTCDate(date.getUTCDate() + 6);
      query.set("to", date.toISOString().slice(0, 10));
    } else {
      query.set("from", today);
      date.setUTCDate(date.getUTCDate() + 30);
      query.set("to", date.toISOString().slice(0, 10));
    }
  }
  const [response, options] = await Promise.all([
    authenticatedFetch("/bookings?" + query),
    authenticatedFetch("/bookings/options"),
  ]);
  if (!response.ok || !options.ok)
    throw new Error("Unable to load facility bookings.");
  const data = await response.json();
  if (Number(page) > data.meta.last_page)
    redirect(
      "/campuses/bookings?" +
        new URLSearchParams({ ...filters, page: String(data.meta.last_page) }),
    );
  const tomorrow = new Date(today + "T00:00:00Z");
  tomorrow.setUTCDate(tomorrow.getUTCDate() + 1);
  return (
    <AdminShell user={user} title="Facilities & bookings">
      <BookingList
        records={data.data}
        meta={data.meta}
        options={(await options.json()).data}
        filters={filters}
        tomorrow={tomorrow.toISOString().slice(0, 10)}
      />
    </AdminShell>
  );
}
export default function Page(props: { searchParams: FoundationSearchParams }) {
  return (
    <Suspense fallback={<LoadingState message="Loading facility bookings…" />}>
      <Content {...props} />
    </Suspense>
  );
}
