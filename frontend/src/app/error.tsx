"use client";

import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";

export default function ErrorPage({ reset }: { reset: () => void }) {
  return (
    <main className="flex min-h-svh items-center justify-center p-6">
      <div className="flex w-full max-w-md flex-col items-start gap-4">
        <Alert variant="destructive">
          <AlertTitle role="heading" aria-level={1}>We couldn’t load your workspace.</AlertTitle>
          <AlertDescription>Please try again in a moment.</AlertDescription>
        </Alert>
        <Button onClick={reset}>Try again</Button>
      </div>
    </main>
  );
}
