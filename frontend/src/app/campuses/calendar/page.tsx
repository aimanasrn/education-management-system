import { FoundationPage, type FoundationSearchParams } from "@/components/foundation-page";

export default function Page({ searchParams }: { searchParams: FoundationSearchParams }) {
  return <FoundationPage entity="closures" searchParams={searchParams} />;
}
