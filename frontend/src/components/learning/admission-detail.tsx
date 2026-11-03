"use client";
import Link from "next/link";
import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import {
  learningMutation,
  uploadAdmissionDocument,
} from "@/app/learning-actions";
import { LearningForm } from "@/components/learning/form-dialog";
import { applicationFields } from "@/components/learning/learning-list";
import { useWorkspace } from "@/components/layout/workspace-context";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
  AlertDialogAction,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from "@/components/ui/card";
import {
  FieldGroup,
  Field,
  FieldLabel,
  FieldDescription,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Spinner } from "@/components/ui/spinner";
import {
  statusLabel,
  type Application,
  type LearningOptions,
  type LearningResult,
} from "@/lib/learning-types";

export function AdmissionDetail({
  application: a,
  options,
}: {
  application: Application;
  options: LearningOptions;
}) {
  const { user } = useWorkspace();
  const router = useRouter();
  const [note, setNote] = useState("");
  const [confirm, setConfirm] = useState<string | null>(null);
  const [state, setState] = useState<LearningResult>({});
  const [pending, start] = useTransition();
  function run(action: string, documentId?: number) {
    start(async () => {
      setState({});
      try {
        const result = await learningMutation(
          documentId ? "document-verify" : "admission-transition",
          a.id,
          documentId ? { document_id: documentId } : { action, note },
        );
        setState(result);
        if (result.success) {
          setConfirm(null);
          router.refresh();
        }
      } catch {
        setState({ error: "The request failed. Try again." });
      }
    });
  }
  function upload(form: HTMLFormElement) {
    const data = new FormData(form);
    start(async () => {
      setState({});
      try {
        const result = await uploadAdmissionDocument(a.id, data);
        setState(result);
        if (result.success) {
          form.reset();
          router.refresh();
        }
      } catch {
        setState({
          error:
            "The upload failed. Choose a PDF, JPEG or PNG up to 5 MB and try again.",
        });
      }
    });
  }
  const actionNames: Record<string, string> = {
    submit: "Submit application",
    review: "Start review",
    approve: "Approve admission",
    reject: "Reject application",
    withdraw: "Withdraw application",
  };
  return (
    <>
      <div>
        <Button
          variant="ghost"
          render={<Link href="/students/admissions" />}
          nativeButton={false}
        >
          Back to admissions
        </Button>
      </div>
      <section className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex flex-col gap-2">
          <h2 className="text-2xl font-semibold">{a.full_name}</h2>
          <div className="flex flex-wrap gap-2">
            <Badge variant="outline">{a.reference}</Badge>
            <Badge variant="secondary">{statusLabel(a.status)}</Badge>
          </div>
        </div>
        {a.access.edit ? (
          <LearningForm
            title="Edit application"
            description="Drafts can be edited before submission."
            operation="admission-save"
            id={a.id}
            initial={{
              full_name: a.full_name,
              email: a.email ?? "",
              phone: a.phone ?? "",
              campus_id: a.campus_id,
              programme_id: a.programme_id,
              requested_start: a.requested_start,
            }}
            fields={applicationFields}
            options={options}
          />
        ) : null}
      </section>
      {state.error ? (
        <Alert variant="destructive" role="alert">
          <AlertDescription>
            {state.error}
            {state.errors
              ? Object.entries(state.errors).map(([key, messages]) => (
                  <p key={key}>{messages.join(" ")}</p>
                ))
              : null}
          </AlertDescription>
        </Alert>
      ) : state.success ? (
        <Alert role="status">
          <AlertDescription>Saved successfully.</AlertDescription>
        </Alert>
      ) : null}
      <Card>
        <CardHeader>
          <CardTitle>Application details</CardTitle>
          <CardDescription>
            Approval creates one student profile. Enrolment and account access
            are assigned separately.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-5 sm:grid-cols-2">
            {[
              ["Campus", a.campus],
              ["Programme", a.programme],
              ["Contact email", a.email ?? "Not provided"],
              ["Contact phone", a.phone ?? "Not provided"],
              ["Requested start", a.requested_start],
              ["Student number", a.student_number ?? "Assigned on approval"],
            ].map(([label, value]) => (
              <div key={label}>
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="mt-1 break-words text-sm">{value}</dd>
              </div>
            ))}
          </dl>
          {a.decision_note ? (
            <p className="mt-5 whitespace-pre-wrap text-sm">
              Decision: {a.decision_note}
            </p>
          ) : null}
          {a.student_id && user.permissions.includes("enrolments.manage") ? (
            <div className="mt-5 flex flex-wrap gap-2">
              <Button
                variant="outline"
                render={<Link href={"/students/records/" + a.student_id} />}
                nativeButton={false}
              >
                View student record
              </Button>
              <Button
                render={
                  <Link
                    href={"/students/enrolments?student_id=" + a.student_id}
                  />
                }
                nativeButton={false}
              >
                Manage enrolment
              </Button>
            </div>
          ) : null}
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>Supporting documents</CardTitle>
          <CardDescription>
            Private attachments. Review verifies the evidence; files are not
            published.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-5">
          {a.documents.length ? (
            <ul className="flex flex-col gap-3">
              {a.documents.map((doc) => (
                <li
                  key={doc.id}
                  className="flex flex-wrap items-center justify-between gap-3"
                >
                  <div className="min-w-0">
                    <p className="break-words text-sm">{doc.name}</p>
                    <p className="text-xs text-muted-foreground">
                      {Math.ceil(doc.bytes / 1024)} KB ·{" "}
                      {doc.verified_at ? "Verified" : "Awaiting verification"}
                    </p>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      render={
                        <a
                          href={
                            "/api/admissions/" + a.id + "/documents/" + doc.id
                          }
                        />
                      }
                      nativeButton={false}
                    >
                      Download
                    </Button>
                    {a.access.decide && !doc.verified_at ? (
                      <Button
                        size="sm"
                        disabled={pending}
                        onClick={() => run("verify", doc.id)}
                      >
                        Verify document
                      </Button>
                    ) : null}
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted-foreground">
              No documents attached.
            </p>
          )}
          {a.access.edit ? (
            <form
              className="flex flex-col gap-3"
              onSubmit={(event) => {
                event.preventDefault();
                upload(event.currentTarget);
              }}
            >
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="admission-file">Add document</FieldLabel>
                  <Input
                    id="admission-file"
                    name="file"
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    required
                    disabled={pending}
                  />
                  <FieldDescription>
                    PDF, JPEG or PNG. Maximum 5 MB per file, 10 files per
                    application. Attach before submission.
                  </FieldDescription>
                </Field>
              </FieldGroup>
              <div>
                <Button
                  type="submit"
                  variant="outline"
                  disabled={pending || a.documents.length >= 10}
                >
                  {pending ? <Spinner /> : null}Upload document
                </Button>
              </div>
            </form>
          ) : null}
        </CardContent>
      </Card>
      {a.access.submit ||
      a.access.review ||
      a.access.decide ||
      a.access.withdraw ? (
        <Card>
          <CardHeader>
            <CardTitle>Next action</CardTitle>
            <CardDescription>
              Submitted applications are locked for review. Approval and
              rejection are final.
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-5">
            {a.access.decide ? (
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="decision-note">Decision note</FieldLabel>
                  <Textarea
                    id="decision-note"
                    value={note}
                    maxLength={2000}
                    onChange={(event) => setNote(event.target.value)}
                    disabled={pending}
                  />
                  <FieldDescription>
                    Visible to the applicant. Required for rejection.
                  </FieldDescription>
                </Field>
              </FieldGroup>
            ) : null}
            <div className="flex flex-wrap gap-2">
              {[
                a.access.submit ? "submit" : null,
                a.access.review ? "review" : null,
                a.access.decide ? "approve" : null,
                a.access.decide ? "reject" : null,
                a.access.withdraw ? "withdraw" : null,
              ]
                .filter((action): action is string => !!action)
                .map((action) => (
                  <Button
                    key={action}
                    variant={
                      action === "reject" || action === "withdraw"
                        ? "outline"
                        : "default"
                    }
                    disabled={pending}
                    onClick={() => setConfirm(action)}
                  >
                    {actionNames[action]}
                  </Button>
                ))}
            </div>
          </CardContent>
        </Card>
      ) : null}
      <AlertDialog
        open={!!confirm}
        onOpenChange={(open) => {
          if (!open && !pending) setConfirm(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{actionNames[confirm ?? ""]}</AlertDialogTitle>
            <AlertDialogDescription>
              {confirm === "approve"
                ? "This creates a student profile with a generated student number. Confirm the applicant identity and documents before proceeding."
                : confirm === "reject"
                  ? "The applicant will see your decision and note. This decision is final."
                  : "Confirm this application status change."}
            </AlertDialogDescription>
          </AlertDialogHeader>
          {state.error ? (
            <Alert variant="destructive">
              <AlertDescription>
                {state.error}
                {state.errors
                  ? Object.values(state.errors).flat().join(" ")
                  : null}
              </AlertDescription>
            </Alert>
          ) : null}
          <AlertDialogFooter>
            <AlertDialogCancel disabled={pending}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              disabled={pending}
              onClick={(event) => {
                event.preventDefault();
                if (confirm) run(confirm);
              }}
            >
              {pending ? <Spinner /> : null}Confirm
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
