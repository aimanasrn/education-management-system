"use client";
import Link from "next/link";
import { LearningForm } from "@/components/learning/form-dialog";
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
  bookingChoices,
  bookingFields,
  bookingTime,
  type Booking,
} from "@/lib/booking-types";
import { statusLabel, type LearningOptions } from "@/lib/learning-types";
export function BookingDetail({
  booking: b,
  options,
}: {
  booking: Booking;
  options: LearningOptions;
}) {
  const reason = [
    { key: "reason", label: "Reason", type: "textarea" as const },
  ];
  return (
    <>
      <div>
        <Button
          variant="ghost"
          render={<Link href="/campuses/bookings" />}
          nativeButton={false}
        >
          Back to bookings
        </Button>
      </div>
      <section className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex flex-col gap-2">
          <h2 className="text-2xl font-semibold">{b.title}</h2>
          <div className="flex gap-2">
            <Badge variant="outline">{statusLabel(b.kind)}</Badge>
            <Badge variant="secondary">{statusLabel(b.status)}</Badge>
          </div>
        </div>
        {b.access.edit ? (
          <LearningForm
            title="Reschedule / edit"
            description="This changes one occurrence. Choose a reason; earlier room, time and status details remain in the booking history."
            operation="booking-save"
            id={b.id}
            initial={{
              title: b.title,
              booking_type: b.kind,
              room_id: b.room_id,
              starts_local: b.starts_local,
              ends_local: b.ends_local,
              attendees: b.attendees,
              priority: b.priority,
              purpose: b.purpose ?? "",
              reason: "",
            }}
            fields={[...bookingFields, ...reason]}
            options={{
              ...options,
              room_id: (options.room_id ?? []).filter(
                (room) => room.campus_id === b.campus_id,
              ),
              ...bookingChoices,
            }}
          />
        ) : null}
      </section>
      <Card>
        <CardHeader>
          <CardTitle>Reservation details</CardTitle>
          <CardDescription>
            {b.status === "requested"
              ? "This request does not hold the room until it is approved."
              : "Room reservations use the confirmed time interval."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-5 sm:grid-cols-2">
            {[
              ["Facility", b.room],
              ["Campus", b.campus],
              ["Start · KL", bookingTime(b.starts_at)],
              ["End · KL", bookingTime(b.ends_at)],
              ...(b.access.private
                ? [
                    ["Requested by", b.created_by ?? "Unavailable"],
                    ["Expected attendees", String(b.attendees)],
                    ["Priority", statusLabel(b.priority ?? "normal")],
                    [
                      "Purpose / maintenance details",
                      b.purpose ?? "Not provided",
                    ],
                    [
                      "Last decision / change reason",
                      b.decision_reason ?? "Not provided",
                    ],
                  ]
                : []),
            ].map(([label, value]) => (
              <div key={label}>
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="mt-1 whitespace-pre-wrap break-words text-sm">
                  {value}
                </dd>
              </div>
            ))}
          </dl>
        </CardContent>
      </Card>
      {b.access.review || b.access.cancel || b.access.complete ? (
        <Card>
          <CardHeader>
            <CardTitle>Actions</CardTitle>
            <CardDescription>
              Every decision requires a reason. Approval rechecks room
              availability, operating hours, closures and capacity.
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-3">
            {[
              ...(b.access.review
                ? [
                    ["approve", "Approve request"],
                    ["decline", "Decline request"],
                  ]
                : []),
              ...(b.access.cancel ? [["cancel", "Cancel reservation"]] : []),
              ...(b.access.complete ? [["complete", "Mark completed"]] : []),
            ].map(([action, title]) => (
              <LearningForm
                key={action}
                title={title}
                description={
                  action === "cancel"
                    ? "Cancel this occurrence and release its reserved time. The record and history are kept."
                    : "Record a reason before confirming this decision."
                }
                operation="booking-transition"
                id={b.id}
                initial={{ action, reason: "" }}
                fields={reason}
              />
            ))}
          </CardContent>
        </Card>
      ) : null}
      {b.access.private ? (
        <Card>
          <CardHeader>
            <CardTitle>Booking history</CardTitle>
            <CardDescription>
              Earlier room IDs, times and status changes are retained.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <ol className="flex flex-col gap-5">
              {b.history?.map((change) => (
                <li key={change.id} className="flex flex-col gap-1">
                  <p className="text-sm font-medium">
                    {statusLabel(change.action)} ·{" "}
                    {bookingTime(
                      change.created_at.endsWith("Z") ||
                        change.created_at.includes("+")
                        ? change.created_at
                        : change.created_at.replace(" ", "T") + "Z",
                    )}
                  </p>
                  {change.before ? (
                    <p className="text-xs text-muted-foreground">
                      Previously: room #{change.before.room_id},{" "}
                      {bookingTime(change.before.starts_at)} –{" "}
                      {bookingTime(change.before.ends_at)} ·{" "}
                      {statusLabel(change.before.status)}
                    </p>
                  ) : null}
                  <p className="text-xs text-muted-foreground">
                    Room #{change.after.room_id},{" "}
                    {bookingTime(change.after.starts_at)} –{" "}
                    {bookingTime(change.after.ends_at)} ·{" "}
                    {statusLabel(change.after.status)}
                  </p>
                  {change.reason ? (
                    <p className="whitespace-pre-wrap text-sm">
                      {change.reason}
                    </p>
                  ) : null}
                </li>
              ))}
            </ol>
          </CardContent>
        </Card>
      ) : null}
    </>
  );
}
