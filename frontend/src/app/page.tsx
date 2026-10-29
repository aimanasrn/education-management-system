import { Suspense } from "react";

async function LaravelStatus() {
  const response = await fetch(
    `${process.env.LARAVEL_API_URL}/health`,
    { cache: "no-store", headers: { Accept: "application/json" } }
  );

  if (!response.ok) {
    throw new Error(`Laravel health check failed (HTTP ${response.status})`);
  }

  const data: { message: string } = await response.json();

  return <p className="text-green-600">{data.message}</p>;
}

export default function Home() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 p-8">
      <h1 className="text-3xl font-bold">
        Education Management System
      </h1>

      <Suspense fallback={<p role="status">Connecting to Laravel...</p>}>
        <LaravelStatus />
      </Suspense>
    </main>
  );
}
