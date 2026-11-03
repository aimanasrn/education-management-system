import { BookOpenIcon, Building2Icon, CalendarDaysIcon, DoorOpenIcon, GraduationCapIcon, LayoutDashboardIcon, LinkIcon, ListChecksIcon, MonitorIcon, SchoolIcon, ShieldCheckIcon, UserRoundCheckIcon, UsersIcon, WrenchIcon, type LucideIcon } from "lucide-react";

export type NavigationItem = { key: string; href?: string; icon: LucideIcon; permission: string; children?: NavigationItem[]; unavailable?: boolean };
export type NavigationGroup = { key: string; items: NavigationItem[] };

export const navigation: NavigationGroup[] = [
  { key: "dashboards", items: [
    { key: "dashboardOverview", href: "/dashboard", icon: LayoutDashboardIcon, permission: "dashboard.view" },
    { key: "campusMonitor", href: "/dashboard/campuses", icon: MonitorIcon, permission: "campuses.view" },
  ] },
  { key: "management", items: [
    { key: "campusDirectory", href: "/campuses", icon: Building2Icon, permission: "campuses.view" },
    { key: "buildingsRooms", icon: SchoolIcon, permission: "campuses.view", children: [
      { key: "buildings", href: "/campuses/buildings", icon: Building2Icon, permission: "campuses.view" },
      { key: "rooms", href: "/campuses/rooms", icon: DoorOpenIcon, permission: "campuses.view" },
    ] },
    { key: "facilities", href: "/campuses/rooms?view=facilities", icon: WrenchIcon, permission: "campuses.view" },
    { key: "facilityBookings", href: "/campuses/bookings", icon: CalendarDaysIcon, permission: "bookings.view" },
  ] },
  { key: "academics", items: [
    { key: "yearsTerms", icon: CalendarDaysIcon, permission: "academics.view", children: [
      { key: "years", href: "/academics/years", icon: CalendarDaysIcon, permission: "academics.view" },
      { key: "terms", href: "/academics/terms", icon: CalendarDaysIcon, permission: "academics.view" },
      { key: "calendar", href: "/campuses/calendar", icon: CalendarDaysIcon, permission: "campuses.view" },
    ] },
    { key: "programmesSubjects", icon: BookOpenIcon, permission: "academics.view", children: [
      { key: "programmes", href: "/academics/programmes", icon: BookOpenIcon, permission: "academics.view" },
      { key: "subjects", href: "/academics/subjects", icon: BookOpenIcon, permission: "academics.view" },
    ] },
    { key: "classesTimetables", icon: GraduationCapIcon, permission: "classes.view", children: [
      { key: "classes", href: "/academics/classes", icon: GraduationCapIcon, permission: "classes.view" },
      { key: "timetables", icon: CalendarDaysIcon, permission: "classes.view", unavailable: true },
    ] },
    { key: "teacherAssignments", href: "/academics/classes?view=teachers", icon: UserRoundCheckIcon, permission: "classes.view" },
  ] },
  { key: "users", items: [
    { key: "admissions", href: "/students/admissions", icon: UserRoundCheckIcon, permission: "admissions.view" },
    { key: "enrolments", href: "/students/enrolments", icon: GraduationCapIcon, permission: "learning.view" },
    { key: "learningRecords", href: "/students/records", icon: BookOpenIcon, permission: "learning.view" },
    { key: "studentProfiles", href: "/students", icon: UsersIcon, permission: "students.manage" },
    { key: "staffUsers", href: "/system/users", icon: ShieldCheckIcon, permission: "users.manage" },
    { key: "guardianLinks", href: "/students/links", icon: LinkIcon, permission: "links.manage" },
    { key: "auditLogs", href: "/system/audit", icon: ListChecksIcon, permission: "audit.view" },
  ] },
];

export function allowedNavigation(permissions: string[]) {
  return navigation.map(group => ({ ...group, items: group.items.filter(item => permissions.includes(item.permission)).map(item => ({ ...item, children: item.children?.filter(child => permissions.includes(child.permission)) })) })).filter(group => group.items.length > 0);
}

export function navigationLinks(permissions: string[]) {
  return allowedNavigation(permissions).flatMap(group => group.items.flatMap(item => item.children ? item.children.filter(child => child.href).map(child => ({ ...child, group: group.key })) : item.href ? [{ ...item, group: group.key }] : []));
}

export function workspaceHref(href: string, campusId: string) {
  const [path, query] = href.split("?");
  if (!campusId || !["/dashboard", "/dashboard/campuses", "/campuses/buildings", "/campuses/rooms", "/campuses/calendar", "/academics/classes", "/students/admissions", "/students/enrolments", "/campuses/bookings"].includes(path)) return href;
  const params = new URLSearchParams(query);
  params.set("campus_id", campusId);
  return path + "?" + params;
}

export function initials(name: string) {
  return name.trim().split(/\s+/).slice(0,2).map(part => part[0]).join("").toUpperCase();
}
