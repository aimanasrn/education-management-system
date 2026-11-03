"use client";

import { useActionState } from "react";
import { logout, type LogoutState } from "@/app/actions";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";

const initialState: LogoutState = {};

export function SignOut({ label = "Sign out", pendingLabel = "Signing out…" }: { label?: string; pendingLabel?: string }) {
  const [state, action, pending] = useActionState(logout, initialState);

  return (
    <form action={action} className="flex w-full flex-col gap-3" aria-busy={pending}>
      {state.error ? (
        <Alert variant="destructive"><AlertDescription>{state.error}</AlertDescription></Alert>
      ) : null}
      <Button variant="outline" type="submit" disabled={pending} className="w-full">
        {pending ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
        {pending ? pendingLabel : label}
      </Button>
    </form>
  );
}
