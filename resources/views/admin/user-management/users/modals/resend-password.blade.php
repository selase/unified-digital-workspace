<x-modal id="resendAccountPasswordModal" title="Resend Password">
    <div class="text-center text-sm text-foreground">
        Are you certain you want to change this user's account password?
        Please be advised that this decision cannot be reversed.
    </div>
    <div class="flex justify-center gap-3 pt-6">
        <button class="kt-btn kt-btn-outline" data-kt-modal-dismiss="true" type="button">Cancel</button>
        <form action="#" method="post" id="resendAccountPasswordForm">
            @csrf
            <button class="kt-btn kt-btn-primary" id="resendAccountPasswordButton" type="submit">Yes Resend Password!</button>
        </form>
    </div>
</x-modal>
