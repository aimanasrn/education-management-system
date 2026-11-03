export type Student = {
  id: number;
  student_number: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  enrollment_date: string;
  status: "active" | "archived";
  archived_at: string | null;
  created_at: string;
  updated_at: string;
};

export type StudentValues = {
  student_number: string;
  full_name: string;
  email: string;
  phone: string;
  enrollment_date: string;
};

export type StudentFormState = {
  error?: string;
  errors?: Partial<Record<keyof StudentValues, string[]>>;
  values?: StudentValues;
  success?: boolean;
};

export type StudentList = {
  data: Student[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
};

export type StudentFilters = { q: string; status: "active" | "archived" | "all"; page: number };

export function studentListUrl(filters: StudentFilters, page = filters.page) {
  const params = new URLSearchParams();
  if (filters.q) params.set("q", filters.q);
  if (filters.status !== "active") params.set("status", filters.status);
  if (page > 1) params.set("page", String(page));
  const query = params.toString();
  return "/students" + (query ? "?" + query : "");
}

export function formatEnrollmentDate(value: string) {
  return new Intl.DateTimeFormat("en-GB", {
    day: "numeric", month: "short", year: "numeric", timeZone: "UTC",
  }).format(new Date(value + "T00:00:00Z"));
}
