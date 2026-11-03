"use client";

import { useActionState } from "react";
import { login, type LoginState } from "@/app/actions";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";

const initialState: LoginState = {};

export function LoginForm() {
  const [state, action, pending] = useActionState(login, initialState);

  return (
    <Card className="w-full">
      <CardHeader>
        <CardTitle role="heading" aria-level={2}>Welcome back</CardTitle>
        <CardDescription>Sign in with your school account.</CardDescription>
      </CardHeader>
      <CardContent>
        <form id="login-form" action={action} aria-busy={pending}>
          <FieldGroup>
            <Field data-invalid={!!state.error}>
              <FieldLabel htmlFor="email">Email address</FieldLabel>
              <Input
                id="email"
                name="email"
                type="email"
                autoComplete="username"
                placeholder="you@school.edu"
                maxLength={254}
                defaultValue={state.email}
                required
                readOnly={pending}
                aria-describedby={state.error ? "login-error" : undefined}
                aria-invalid={!!state.error}
              />
            </Field>
            <Field data-invalid={!!state.error}>
              <FieldLabel htmlFor="password">Password</FieldLabel>
              <Input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                maxLength={72}
                required
                readOnly={pending}
                aria-describedby={state.error ? "login-error" : undefined}
                aria-invalid={!!state.error}
              />
            </Field>
            {state.error ? <FieldError id="login-error">{state.error}</FieldError> : null}
            <Field>
              <Button size="lg" type="submit" disabled={pending}>
                {pending ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
                {pending ? "Signing in…" : "Sign in"}
              </Button>
            </Field>
          </FieldGroup>
        </form>
      </CardContent>
      <CardFooter>
        <p className="text-xs text-muted-foreground">
          Need access? Contact your administrator.
        </p>
      </CardFooter>
    </Card>
  );
}
