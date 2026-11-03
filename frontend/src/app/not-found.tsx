import Link from "next/link";
import { Button } from "@/components/ui/button";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from "@/components/ui/empty";

export default function NotFound() {
  return (
    <main className="flex min-h-svh items-center justify-center p-6">
      <Empty className="max-w-lg">
        <EmptyHeader>
          <EmptyTitle role="heading" aria-level={1}>Page not found</EmptyTitle>
          <EmptyDescription>The page you’re looking for doesn’t exist.</EmptyDescription>
        </EmptyHeader>
        <EmptyContent>
          <Button render={<Link href="/" />} nativeButton={false}>Back to home</Button>
        </EmptyContent>
      </Empty>
    </main>
  );
}
