import { useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { AlertDialog, Button, Flex, Spinner, Text } from '@radix-ui/themes';

/**
 * A destructive-action button guarded by an in-app confirmation dialog.
 *
 * Canvas embeds page extensions in an iframe whose sandbox attribute omits
 * `allow-modals`, so `window.confirm()` is ignored by the browser and returns
 * false. Any button gated on its return value therefore does nothing at all.
 *
 * @see ui/src/components/extensions/ExtensionPage.tsx in the canvas module.
 */
export default function ConfirmButton({
  title,
  description,
  confirmLabel = 'Delete',
  onConfirm,
  loadDetails,
  children,
  ...triggerProps
}: {
  title: string;
  description: string;
  confirmLabel?: string;
  onConfirm: () => Promise<void> | void;
  /**
   * Optional extra context resolved when the dialog opens.
   *
   * Used for checks that are too expensive to run for every row on render,
   * such as scanning content for icon usage.
   */
  loadDetails?: () => Promise<ReactNode>;
  children: ReactNode;
} & ComponentProps<typeof Button>) {
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [details, setDetails] = useState<ReactNode>(null);
  const [loadingDetails, setLoadingDetails] = useState(false);

  const handleOpenChange = (next: boolean) => {
    setOpen(next);
    if (!next || !loadDetails) return;
    setDetails(null);
    setLoadingDetails(true);
    Promise.resolve()
      .then(loadDetails)
      .then(setDetails)
      .catch(() => setDetails(<Text size="2" color="orange">Could not check where this is used.</Text>))
      .finally(() => setLoadingDetails(false));
  };

  return (
    <AlertDialog.Root open={open} onOpenChange={handleOpenChange}>
      <AlertDialog.Trigger>
        <Button {...triggerProps}>{children}</Button>
      </AlertDialog.Trigger>
      <AlertDialog.Content maxWidth="520px">
        <AlertDialog.Title>{title}</AlertDialog.Title>
        <AlertDialog.Description size="2">{description}</AlertDialog.Description>
        {loadingDetails && (
          <Flex gap="2" align="center" mt="3">
            <Spinner size="1" />
            <Text size="2" color="gray">Checking where this is used…</Text>
          </Flex>
        )}
        {!loadingDetails && details && <div className="confirm-details">{details}</div>}
        <Flex gap="3" mt="4" justify="end">
          <AlertDialog.Cancel>
            <Button variant="soft" color="gray" disabled={busy}>
              Cancel
            </Button>
          </AlertDialog.Cancel>
          <Button
            color="red"
            loading={busy}
            onClick={async () => {
              setBusy(true);
              try {
                await onConfirm();
              }
              finally {
                setBusy(false);
                setOpen(false);
              }
            }}
          >
            {confirmLabel}
          </Button>
        </Flex>
      </AlertDialog.Content>
    </AlertDialog.Root>
  );
}
