import "server-only";

export async function laravelFetch(path: string, init: RequestInit = {}) {
  const baseUrl = process.env.LARAVEL_API_URL;

  if (!baseUrl) {
    throw new Error("LARAVEL_API_URL is not configured.");
  }

  return fetch(`${baseUrl.replace(/\/$/, "")}${path}`, {
    ...init,
    cache: "no-store",
    signal: AbortSignal.timeout(10_000),
    headers: {
      Accept: "application/json",
      ...init.headers,
    },
  });
}
