import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type Props = {
    client: { id: string; name: string };
    scopes: { id: string; description: string }[];
    workspace: { name: string | null };
    authToken: string;
    state: string;
    csrfToken: string;
};

/**
 * Passport's consent screen (RFC 0003, RFC 0012): which workspace the app
 * gets, and what it may do there, in words. Approving and denying are
 * ordinary form posts, because the answer redirects back to the app.
 */
export default function Authorize({
    client,
    scopes,
    workspace,
    authToken,
    state,
    csrfToken,
}: Props) {
    const fields = (
        <>
            <input type="hidden" name="_token" value={csrfToken} />
            <input type="hidden" name="state" value={state} />
            <input type="hidden" name="client_id" value={client.id} />
            <input type="hidden" name="auth_token" value={authToken} />
        </>
    );

    return (
        <>
            <Head title={`Allow ${client.name}`} />

            <div className="space-y-6">
                <p className="text-sm text-muted-foreground">
                    <strong className="text-foreground">{client.name}</strong>{' '}
                    is asking to act as you in{' '}
                    <strong className="text-foreground">
                        {workspace.name}
                    </strong>
                    . It will be able to:
                </p>

                {scopes.length > 0 ? (
                    <ul className="list-disc space-y-1 pl-5 text-sm">
                        {scopes.map((scope) => (
                            <li key={scope.id}>{scope.description}</li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm">See who you are in the workspace.</p>
                )}

                <div className="flex gap-3">
                    <form method="post" action="/oauth/authorize">
                        {fields}
                        <Button type="submit">Allow</Button>
                    </form>

                    <form method="post" action="/oauth/authorize">
                        {fields}
                        <input type="hidden" name="_method" value="DELETE" />
                        <Button type="submit" variant="outline">
                            Deny
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}

Authorize.layout = {
    title: 'Allow access',
    description: 'An app wants to use Longhand for you',
};
