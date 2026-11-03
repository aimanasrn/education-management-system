import { LearningPage } from "@/components/learning/learning-page";
import type { FoundationSearchParams } from "@/components/foundation-page";
export default function Page(props: { searchParams: FoundationSearchParams }) {
  return <LearningPage resource="admissions" {...props} />;
}
