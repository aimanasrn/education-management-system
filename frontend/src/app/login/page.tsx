import { Suspense } from "react";
import { redirect } from "next/navigation";
import { Brand } from "@/components/brand";
import { LoginForm } from "@/components/login-form";
import { LoginSkeleton } from "@/components/loading-state";
import { Separator } from "@/components/ui/separator";
import { currentUser } from "@/lib/auth";

async function SignIn() {
  const user = await currentUser();
  if (user) redirect("/dashboard");
  return <LoginForm />;
}

export default function LoginPage() {
  return (
    <div className="flex min-h-svh flex-col">
      <header className="px-6 py-4 sm:px-12"><Brand /></header>
      <Separator />
      <main className="mx-auto grid w-full max-w-6xl flex-1 items-center gap-10 px-6 py-12 md:grid-cols-[minmax(0,1fr)_minmax(0,24rem)] md:gap-16 md:px-12">
        <div className="flex max-w-xl flex-col gap-5">
          <h1 className="text-4xl leading-tight font-semibold tracking-tight lg:text-5xl">
            A clear start to your school day.
          </h1>
          <p className="text-lg text-muted-foreground">
            One place to manage your school community.
          </p>
        </div>
        <Suspense fallback={<LoginSkeleton />}><SignIn /></Suspense>
      </main>
    </div>
  );
}
