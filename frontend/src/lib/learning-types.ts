import type { Option } from "@/lib/foundation-config";
export type Application = {
  id: number;
  reference: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  campus_id: number;
  campus: string;
  programme_id: number;
  programme: string;
  requested_start: string;
  status: string;
  decision_note: string | null;
  student_id: number | null;
  student_number: string | null;
  submitted_at: string | null;
  reviewed_at: string | null;
  documents: {
    id: number;
    name: string;
    bytes: number;
    verified_at: string | null;
  }[];
  access: {
    edit: boolean;
    submit: boolean;
    withdraw: boolean;
    review: boolean;
    decide: boolean;
  };
};
export type Learner = {
  id: number;
  full_name: string;
  student_number: string;
  status: string;
  email: string | null;
  phone: string | null;
  contacts_visible: boolean;
  enrollment_date: string;
  account: { user_id: number; name: string } | null;
  guardians: {
    user_id: number;
    name: string;
    relationship: string;
    emergency_contact: boolean;
    status: string;
  }[];
};
export type Enrolment = {
  id: number;
  student_id: number;
  student: string;
  student_number: string;
  school_class_id: number;
  class: string;
  campus: string;
  programme: string;
  term: string;
  starts_on: string;
  ends_on: string;
  status: string;
  end_reason: string | null;
  previous_enrolment_id: number | null;
};
export type LearningResource =
  "admissions" | "enrolments" | "records" | "links";
export type LearningRecord = Application | Learner | Enrolment;
export type LearningList = {
  data: LearningRecord[];
  meta: { total: number; current_page: number; last_page: number };
};
export type LearningOptions = Record<
  string,
  (Option & { starts_on?: string; ends_on?: string })[]
>;
export type LearningResult = {
  success?: boolean;
  id?: number;
  error?: string;
  errors?: Record<string, string[]>;
};
export type LearningOperation =
  | "admission-save"
  | "admission-transition"
  | "document-verify"
  | "enrol"
  | "enrolment-change"
  | "link"
  | "booking-save"
  | "booking-transition";
export const learningModules = {
  admissions: {
    title: "Admissions",
    description:
      "Submit applications, review documents and approve new student profiles.",
    path: "/students/admissions",
    permission: "admissions.view",
    endpoint: "/admissions",
  },
  enrolments: {
    title: "Enrolments",
    description:
      "Historical class placements, programme enrolments and transfers. Dates include both the first and last day.",
    path: "/students/enrolments",
    permission: "learning.view",
    endpoint: "/learning/enrolments",
  },
  records: {
    title: "Learning records",
    description:
      "Student records available through your role, current class assignment, self link or guardian link.",
    path: "/students/records",
    permission: "learning.view",
    endpoint: "/learning/students",
  },
  links: {
    title: "Guardian & student links",
    description:
      "Verify identities before granting access. Guardian links can be deactivated; each student login identifies one profile.",
    path: "/students/links",
    permission: "links.manage",
    endpoint: "/learning/students",
  },
} as const;
export function statusLabel(value: string) {
  return value
    .replaceAll("_", " ")
    .replace(/^./, (letter) => letter.toUpperCase());
}
