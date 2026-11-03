import type { FieldSpec } from "@/lib/foundation-config";
export type Booking = {
  id: number;
  campus_id: number;
  room_id: number;
  room: string;
  campus: string;
  kind: string;
  title: string;
  purpose: string | null;
  attendees: number | null;
  priority: string | null;
  created_by: string | null;
  starts_at: string;
  ends_at: string;
  starts_local: string;
  ends_local: string;
  status: string;
  series_id: string | null;
  decision_reason: string | null;
  access: {
    private: boolean;
    edit: boolean;
    review: boolean;
    cancel: boolean;
    complete: boolean;
  };
  history?: {
    id: number;
    action: string;
    reason: string | null;
    before: {
      room_id: number;
      starts_at: string;
      ends_at: string;
      status: string;
    } | null;
    after: {
      room_id: number;
      starts_at: string;
      ends_at: string;
      status: string;
    };
    created_at: string;
  }[];
};
export const bookingFields: FieldSpec[] = [
  { key: "title", label: "Booking title" },
  { key: "booking_type", label: "Booking type", type: "select" },
  { key: "room_id", label: "Facility / room", type: "select" },
  {
    key: "starts_local",
    label: "Start · Kuala Lumpur",
    type: "datetime-local",
  },
  { key: "ends_local", label: "End · Kuala Lumpur", type: "datetime-local" },
  { key: "attendees", label: "Expected attendees", type: "number" },
  { key: "priority", label: "Priority", type: "select" },
  {
    key: "purpose",
    label: "Purpose / maintenance details",
    type: "textarea",
    optional: true,
  },
];
export const bookingChoices = {
  booking_type: [
    { value: "booking", label: "Facility booking" },
    { value: "event", label: "Campus event" },
    { value: "maintenance", label: "Planned maintenance" },
  ],
  priority: [
    { value: "normal", label: "Normal" },
    { value: "high", label: "High" },
    { value: "urgent", label: "Urgent" },
  ],
};
export function bookingTime(value: string) {
  return new Intl.DateTimeFormat("en-MY", {
    timeZone: "Asia/Kuala_Lumpur",
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}
