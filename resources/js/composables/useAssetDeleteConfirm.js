import { useConfirmDialog } from './useConfirmDialog.js';

export function useAssetDeleteConfirm() {
    const { state, ask, close, confirm } = useConfirmDialog();

    const assetLabel = (asset) => {
        const tag = asset?.asset_tag ? `${asset.asset_tag} — ` : '';

        return `${tag}${asset?.name ?? 'this asset'}`;
    };

    const confirmDelete = (asset, action) => {
        ask({
            title: 'Delete asset?',
            message: `Permanently delete ${assetLabel(asset)}? Linked tickets will be unlinked. This cannot be undone.`,
            confirmLabel: 'Delete asset',
            variant: 'danger',
            action,
        });
    };

    const confirmUnlinkFromTicket = (asset, action) => {
        ask({
            title: 'Remove linked asset?',
            message: `Remove ${assetLabel(asset)} from this ticket? The asset record will not be deleted.`,
            confirmLabel: 'Remove',
            variant: 'danger',
            action,
        });
    };

    return {
        state,
        close,
        confirm,
        confirmDelete,
        confirmUnlinkFromTicket,
    };
}
