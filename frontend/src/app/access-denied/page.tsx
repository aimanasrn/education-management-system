import Link from "next/link";
import { Suspense } from "react";
import { Brand } from "@/components/brand";
import { LoadingState } from "@/components/loading-state";
import { SignOut } from "@/components/sign-out";
import { Button } from "@/components/ui/button";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from "@/components/ui/empty";
import { requireUser } from "@/lib/auth";

async function AccessNotice() {
  const user = await requireUser();
  return <main className="mx-auto flex min-h-svh max-w-2xl flex-col gap-12 px-6 py-10"><Brand /><Empty><EmptyHeader><EmptyTitle role="heading" aria-level={1}>Access is restricted</EmptyTitle><EmptyDescription>You are signed in as {user.name}. Your account does not have permission for this area.</EmptyDescription><EmptyDescription>Contact your centre administrator if your role or campus assignment needs to change.</EmptyDescription></EmptyHeader><EmptyContent><Button render={<Link href="/dashboard" />} nativeButton={false}>Back to overview</Button><SignOut /></EmptyContent></Empty></main>;
}
export default function AccessDeniedPage() {
  return <Suspense fallback={<LoadingState message="Checking your account…" />}><AccessNotice /></Suspense>;
}
