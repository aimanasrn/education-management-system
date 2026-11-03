import type { ReactNode } from "react";
import { WorkspaceFrame } from "@/components/layout/workspace-frame";
import { getWorkspaceChrome } from "@/lib/workspace-data";
import type { User } from "@/lib/auth";

export async function AdminShell({ user, title, children }: { user: User; title: string; children: ReactNode }) {
  const chrome = await getWorkspaceChrome(user);
  return <WorkspaceFrame user={user} title={title} chrome={chrome}>{children}</WorkspaceFrame>;
}
