"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { laravelFetch } from "@/lib/api";
import { SESSION_COOKIE, sessionToken, type User } from "@/lib/auth";

export type LoginState = { error?: string; email?: string };
export type LogoutState = { error?: string };

export async function login(
  _previous: LoginState,
  form: FormData,
): Promise<LoginState> {
  const email = String(form.get("email") ?? "").trim().toLowerCase();
  const password = String(form.get("password") ?? "");

  if (!email || email.length > 254 || !password || new TextEncoder().encode(password).length > 72) {
    return { error: "Enter a valid email address and password.", email };
  }

  let response: Response;
  try {
    response = await laravelFetch("/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });
  } catch {
    return { error: "We couldn't connect. Please try again in a moment.", email };
  }

  if (response.status === 429) {
    return { error: "Too many sign-in attempts. Please wait a minute and try again.", email };
  }
  if (response.status === 422 || response.status === 401) {
    return { error: "The email address or password is incorrect.", email };
  }
  if (!response.ok) {
    return { error: "Sign-in is unavailable right now. Please try again.", email };
  }

  const payload: { token: string; expires_at: string; user: User } = await response.json();
  (await cookies()).set(SESSION_COOKIE, payload.token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.COOKIE_SECURE
      ? process.env.COOKIE_SECURE === "true"
      : process.env.NODE_ENV === "production",
    path: "/",
    expires: new Date(payload.expires_at),
  });

  redirect("/dashboard");
}

export async function logout(): Promise<LogoutState> {
  const token = await sessionToken();
  if (token) {
    try {
      const response = await laravelFetch("/logout", {
        method: "POST",
        headers: { Authorization: `Bearer ${token}` },
      });
      if (!response.ok && response.status !== 401) {
        return { error: "We couldn't sign you out. Please try again." };
      }
    } catch {
      return { error: "We couldn't connect to sign you out. Please try again." };
    }
  }

  (await cookies()).delete(SESSION_COOKIE);
  redirect("/login");
}
