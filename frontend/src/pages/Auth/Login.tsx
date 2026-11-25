import LoginForm from '@/components/login/LoginForm';
import AuthLayout from '@/layouts/AuthLayout/AuthLayout';

/** 00.1 dan 00.2 Login. */
export default function Login({ status }: { status?: string | null }) {
    return (
        <AuthLayout title="Masuk">
            <LoginForm status={status} />
        </AuthLayout>
    );
}
