import {
  Card,
  CardContent,
  CardHeader,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";

export function LoadingState({ message }: { message: string }) {
  return (
    <main className="flex min-h-svh items-center justify-center p-6">
      <div role="status" className="flex w-full max-w-md flex-col gap-6">
        <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
          <Spinner aria-hidden="true" /><span>{message}</span>
        </div>
        <Card aria-hidden="true">
          <CardHeader><Skeleton className="h-5 w-1/2" /></CardHeader>
          <CardContent className="flex flex-col gap-4">
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-8 w-full" />
          </CardContent>
        </Card>
      </div>
    </main>
  );
}

export function LoginSkeleton() {
  return (
    <div role="status" aria-label="Preparing sign-in">
      <span className="sr-only">Preparing sign-in…</span>
      <Card aria-hidden="true">
        <CardHeader className="gap-2">
          <Skeleton className="h-5 w-1/2" />
          <Skeleton className="h-4 w-3/4" />
        </CardHeader>
        <CardContent className="flex flex-col gap-5">
          <Skeleton className="h-14 w-full" />
          <Skeleton className="h-14 w-full" />
          <Skeleton className="h-9 w-full" />
        </CardContent>
      </Card>
    </div>
  );
}
