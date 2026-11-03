import { authenticatedFetch, requirePermission } from "@/lib/auth";
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string; documentId: string }> },
) {
  await requirePermission("admissions.view");
  const { id, documentId } = await params;
  if (!/^[1-9]\d{0,15}$/.test(id) || !/^[1-9]\d{0,15}$/.test(documentId))
    return new Response("Not found", { status: 404 });
  const response = await authenticatedFetch(
    "/admissions/" + id + "/documents/" + documentId,
  );
  if (!response.ok)
    return new Response(
      response.status === 404 ? "Document not found" : "Document unavailable",
      { status: response.status, headers: { "Cache-Control": "no-store" } },
    );
  return new Response(response.body, {
    headers: {
      "Content-Type": "application/octet-stream",
      "Content-Disposition":
        response.headers.get("Content-Disposition") ??
        'attachment; filename="document"',
      "Cache-Control": "private, no-store",
      "X-Content-Type-Options": "nosniff",
    },
  });
}
