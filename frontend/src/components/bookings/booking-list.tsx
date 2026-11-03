"use client";
import Link from "next/link";
import { useWorkspace } from "@/components/layout/workspace-context";
import { LearningForm } from "@/components/learning/form-dialog";
import { ListFilters, type ListFilter } from "@/components/list-filters";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from "@/components/ui/card";
import {
  Empty,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
} from "@/components/ui/empty";
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationPrevious,
  PaginationNext,
} from "@/components/ui/pagination";
import {
  Table,
  TableHead,
  TableHeader,
  TableBody,
  TableRow,
  TableCell,
} from "@/components/ui/table";
import {
  bookingFields,
  bookingChoices,
  bookingTime,
  type Booking,
} from "@/lib/booking-types";
import { statusLabel, type LearningOptions } from "@/lib/learning-types";
export function BookingList({
  records,
  meta,
  options,
  filters,
  tomorrow,
}: {
  records: Booking[];
  meta: { total: number; current_page: number; last_page: number };
  options: LearningOptions;
  filters: Record<string, string>;
  tomorrow: string;
}) {
  const { user, chrome } = useWorkspace();
  const manager = user.permissions.includes("bookings.manage");
  const selects: ListFilter[] = [
    {
      name: "period",
      label: "Period",
      defaultValue: "next30",
      options: [
        { value: "next30", label: "Next 30 days" },
        { value: "week", label: "This week" },
        { value: "nextweek", label: "Next week" },
        { value: "all", label: "All dates" },
      ],
    },
    {
      name: "status",
      label: "Status",
      defaultValue: "all",
      options: [
        "all",
        "requested",
        "confirmed",
        "declined",
        "cancelled",
        "completed",
      ].map((value) => ({ value, label: statusLabel(value) })),
    },
    {
      name: "booking_type",
      label: "Type",
      defaultValue: "all",
      options: [
        { value: "all", label: "All types" },
        ...bookingChoices.booking_type,
      ],
    },
    {
      name: "room_id",
      label: "Facility",
      defaultValue: "",
      options: [
        { value: "", label: "All available facilities" },
        ...(options.room_id ?? []).map((r) => ({
          value: String(r.value),
          label: r.label,
        })),
      ],
    },
    ...(chrome.campuses.length
      ? [
          {
            name: "campus_id",
            label: "Campus",
            defaultValue: "",
            options: [
              { value: "", label: "All available campuses" },
              ...chrome.campuses.map((c) => ({
                value: String(c.id),
                label: c.label,
              })),
            ],
          },
        ]
      : []),
  ];
  const url = (page: number) =>
    "/campuses/bookings?" +
    new URLSearchParams({ ...filters, page: String(page) });
  return (
    <>
      <section className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex flex-col gap-2">
          <h2 className="text-2xl font-semibold">Facilities & bookings</h2>
          <p className="max-w-2xl text-sm text-muted-foreground">
            Book campus spaces, allocate event rooms and plan maintenance. Times
            shown in Kuala Lumpur.
          </p>
        </div>
        <LearningForm
          title={manager ? "Reserve facility" : "Request facility"}
          description={
            manager
              ? "Reservations are confirmed immediately when the room is available. Weekly series save together; one conflict rejects the entire series."
              : "Requests require campus approval and do not reserve the room until confirmed."
          }
          operation="booking-save"
          initial={{
            title: "",
            booking_type: "booking",
            room_id: "",
            starts_local: tomorrow + "T09:00",
            ends_local: tomorrow + "T10:00",
            attendees: 1,
            priority: "normal",
            purpose: "",
            repeat_weeks: 1,
          }}
          fields={[
            ...bookingFields,
            {
              key: "repeat_weeks",
              label: "Weekly occurrences (1–12)",
              type: "number",
            },
          ]}
          options={{ ...options, ...bookingChoices }}
          redirectTo="/campuses/bookings/"
        />
      </section>
      <Card>
        <CardHeader>
          <CardTitle>Availability and requests</CardTitle>
          <CardDescription>
            Confirmed reservations block the room. Other users’ private details
            are hidden; your campus team can review requests.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ListFilters
            path="/campuses/bookings"
            filters={filters}
            selects={selects}
            placeholder={
              manager ? "Booking title" : "Search your booking titles"
            }
          />
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>{meta.total} reservations</CardTitle>
          <CardDescription>
            Page {meta.current_page} of {meta.last_page}. Open a reservation to
            review, reschedule, cancel or view its history.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {records.length ? (
            <Table>
              <TableHeader>
                <TableRow>
                  {[
                    "Reservation",
                    "Facility / campus",
                    "Start",
                    "End",
                    "Status",
                    "Action",
                  ].map((t) => (
                    <TableHead key={t}>{t}</TableHead>
                  ))}
                </TableRow>
              </TableHeader>
              <TableBody>
                {records.map((b) => (
                  <TableRow key={b.id}>
                    <TableCell className="max-w-64 whitespace-normal">
                      <p>{b.title}</p>
                      <p className="text-xs text-muted-foreground">
                        {statusLabel(b.kind)}
                        {b.series_id ? " · Weekly series" : ""}
                      </p>
                    </TableCell>
                    <TableCell className="whitespace-normal">
                      {b.room}
                      <p className="text-xs text-muted-foreground">
                        {b.campus}
                      </p>
                    </TableCell>
                    <TableCell className="whitespace-normal">
                      {bookingTime(b.starts_at)}
                    </TableCell>
                    <TableCell className="whitespace-normal">
                      {bookingTime(b.ends_at)}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          b.status === "confirmed" ? "success" : "secondary"
                        }
                      >
                        {statusLabel(b.status)}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Button
                        variant="outline"
                        size="sm"
                        render={<Link href={"/campuses/bookings/" + b.id} />}
                        nativeButton={false}
                      >
                        Open reservation
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <Empty>
              <EmptyHeader>
                <EmptyTitle>No reservations found</EmptyTitle>
                <EmptyDescription>
                  Change the period or filters, or submit a facility request. If
                  no facilities are available, ask your administrator to check
                  campus access.
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          )}
          {meta.last_page > 1 ? (
            <Pagination className="mt-5">
              <PaginationContent>
                {meta.current_page > 1 ? (
                  <PaginationItem>
                    <PaginationPrevious href={url(meta.current_page - 1)} />
                  </PaginationItem>
                ) : null}
                {meta.current_page < meta.last_page ? (
                  <PaginationItem>
                    <PaginationNext href={url(meta.current_page + 1)} />
                  </PaginationItem>
                ) : null}
              </PaginationContent>
            </Pagination>
          ) : null}
        </CardContent>
      </Card>
    </>
  );
}
