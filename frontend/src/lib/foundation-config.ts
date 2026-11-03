export type FieldSpec = { key: string; label: string; type?: "text" | "email" | "tel" | "number" | "date" | "textarea" | "select" | "multi" | "hours" | "boolean" | "password" | "facilities" | "datetime-local"; optional?: boolean };
export type EntityConfig = { title: string; singular: string; group: string; path: string; permission: string; description: string; fields: FieldSpec[]; columns: string[] };
const campus: FieldSpec = { key: "campus_id", label: "Campus", type: "select" };
const code: FieldSpec = { key: "code", label: "Code" };
const name: FieldSpec = { key: "name", label: "Name" };
const status: FieldSpec = { key: "status", label: "Status", type: "select" };
const dates: FieldSpec[] = [{ key: "starts_on", label: "Start date", type: "date" }, { key: "ends_on", label: "End date", type: "date" }];
const year: FieldSpec = { key: "academic_year_id", label: "Academic year", type: "select" };
const programme: FieldSpec = { key: "programme_id", label: "Programme", type: "select" };
const capacity: FieldSpec = { key: "capacity", label: "Capacity", type: "number" };

export const entityConfigs = {
  campuses: { title: "Campuses", singular: "campus", group: "Campus management", path: "/campuses", permission: "campuses.view", description: "Centre locations, contacts and weekly operating hours.", fields: [code, name, { key: "email", label: "Email", type: "email", optional: true }, { key: "phone", label: "Phone", type: "tel", optional: true }, { key: "address", label: "Address", type: "textarea", optional: true }, { key: "operating_hours", label: "Operating hours", type: "hours" }, status], columns: ["code", "name", "phone", "status"] },
  buildings: { title: "Buildings", singular: "building", group: "Campus management", path: "/campuses/buildings", permission: "campuses.view", description: "Buildings within your available campuses.", fields: [campus, code, name, status], columns: ["code", "name", "campus_id", "status"] },
  rooms: { title: "Rooms", singular: "room", group: "Campus management", path: "/campuses/rooms", permission: "campuses.view", description: "Classrooms, capacity and facilities.", fields: [campus, { key: "building_id", label: "Building", type: "select", optional: true }, code, name, capacity, { key: "facilities", label: "Facilities", type: "facilities", optional: true }, status], columns: ["code", "name", "campus_id", "capacity", "status"] },
  closures: { title: "Holidays and closures", singular: "calendar entry", group: "Campus management", path: "/campuses/calendar", permission: "campuses.view", description: "Campus calendar dates for future timetable planning.", fields: [campus, name, { key: "kind", label: "Type", type: "select" }, ...dates, status], columns: ["name", "campus_id", "kind", "starts_on", "ends_on", "status"] },
  years: { title: "Academic years", singular: "academic year", group: "Academics", path: "/academics/years", permission: "academics.view", description: "Year boundaries that contain your academic terms.", fields: [code, name, ...dates, status], columns: ["code", "name", "starts_on", "ends_on", "status"] },
  terms: { title: "Terms", singular: "term", group: "Academics", path: "/academics/terms", permission: "academics.view", description: "Teaching periods within an academic year.", fields: [year, code, name, ...dates, status], columns: ["code", "name", "academic_year_id", "starts_on", "ends_on", "status"] },
  programmes: { title: "Programmes", singular: "programme", group: "Academics", path: "/academics/programmes", permission: "academics.view", description: "The learning programmes offered by your centre.", fields: [code, name, { key: "description", label: "Description", type: "textarea", optional: true }, status], columns: ["code", "name", "status"] },
  subjects: { title: "Subjects", singular: "subject", group: "Academics", path: "/academics/subjects", permission: "academics.view", description: "Subjects belonging to each programme.", fields: [programme, code, name, status], columns: ["code", "name", "programme_id", "status"] },
  classes: { title: "Classes", singular: "class", group: "Academics", path: "/academics/classes", permission: "classes.view", description: "Classes, rooms and assigned teachers. Enrolments and sessions follow in the next phases.", fields: [campus, year, { key: "academic_term_id", label: "Term", type: "select" }, programme, { key: "subject_id", label: "Subject", type: "select", optional: true }, { key: "room_id", label: "Room", type: "select", optional: true }, code, name, capacity, { key: "teacher_ids", label: "Teachers", type: "multi", optional: true }, status], columns: ["code", "name", "campus_id", "academic_term_id", "capacity", "teacher_ids", "status"] },
  users: { title: "Users and access", singular: "user", group: "System", path: "/system/users", permission: "users.manage", description: "Assign roles and campuses. Access changes end affected sessions.", fields: [name, { key: "email", label: "Email", type: "email" }, { key: "password", label: "Initial password", type: "password" }, { key: "roles", label: "Roles", type: "multi" }, { key: "campus_ids", label: "Assigned campuses", type: "multi", optional: true }, { key: "is_active", label: "Account enabled", type: "boolean" }], columns: ["name", "email", "roles", "campus_ids", "is_active"] },
} satisfies Record<string, EntityConfig>;
export type EntityKey = keyof typeof entityConfigs;
export function entityPresentation(entity: EntityKey, view?: string): EntityConfig {
  const config = entityConfigs[entity];
  if (entity === "rooms" && view === "facilities") return { ...config, title: "Facilities", description: "Facilities available in each room across your campuses.", columns: ["name", "campus_id", "building_id", "facilities", "capacity", "status"] };
  if (entity === "classes" && view === "teachers") return { ...config, title: "Teacher assignments", description: "Teachers assigned to the classes available to your account.", columns: ["name", "campus_id", "programme_id", "teacher_ids", "status"] };
  return config;
}
export type Option = { value: string | number; label: string; campus_id?: number | null; academic_year_id?: number | null; programme_id?: number | null; campus_ids?: number[] };
export type EntityRecord = { id: number; name: string; labels?: Record<string, string | null>; [key: string]: unknown };
export type EntityList = { data: EntityRecord[]; meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null }; access?: { create: boolean; edit: boolean } };
export type SaveResult = { success?: boolean; error?: string; errors?: Record<string, string[]> };
export const weekdays = [["mon", "Monday"], ["tue", "Tuesday"], ["wed", "Wednesday"], ["thu", "Thursday"], ["fri", "Friday"], ["sat", "Saturday"], ["sun", "Sunday"]] as const;
export type Hours = Record<string, { closed: boolean; opens: string | null; closes: string | null }>;

export function initialValues(key: EntityKey, record?: EntityRecord): Record<string, unknown> {
  return Object.fromEntries(entityConfigs[key].fields.map((field: FieldSpec) => {
    const value = record?.[field.key] ?? (field.key === "status" ? "active" : field.key === "kind" ? "holiday" : field.type === "multi" || field.type === "facilities" ? [] : field.type === "hours" ? Object.fromEntries(weekdays.map(([day]) => [day, { closed: true, opens: null, closes: null }])) : field.type === "boolean" ? true : "");
    return [field.key, value];
  }).filter(([field]) => !(record && field === "password")));
}

export function entityUrl(key: EntityKey, filters: Record<string, string>, page?: number) {
  return entityConfigs[key].path + "?" + new URLSearchParams({ ...filters, page: String(page ?? 1) });
}
export function fieldLabel(key: EntityKey, field: string) {
  return (entityConfigs[key].fields as FieldSpec[]).find(item => item.key === field)?.label ?? field;
}
export function displayValue(record: EntityRecord, field: string): string {
  const relations: Record<string, string> = { campus_id: "campus", building_id: "building", academic_year_id: "academicYear", academic_term_id: "academicTerm", programme_id: "programme", subject_id: "subject", room_id: "room", teacher_ids: "teachers" };
  const relation = relations[field];
  if (relation) return record.labels?.[relation] || "—";
  if (field === "roles") return (record.role_labels as string[]).join(", ");
  if (field === "campus_ids") return (record.campus_names as string[]).join(", ") || "None assigned";
  const value = record[field];
  if (field === "operating_hours") return weekdays.map(([day, label]) => {
    const hours = (value as Hours | null)?.[day];
    return label + ": " + (!hours || hours.closed ? "Closed" : hours.opens + "–" + hours.closes);
  }).join(" · ");
  if (Array.isArray(value)) return value.join(", ") || "—";
  if (typeof value === "boolean") return value ? "Enabled" : "Disabled";
  return value === null || value === "" ? "—" : String(value);
}
