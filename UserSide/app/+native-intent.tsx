export async function redirectSystemPath({
  path,
  initial,
}: {
  path: string;
  initial: boolean;
}) {
  console.log('🔗 [NativeIntent] Received incoming deep link path:', path, 'initial:', initial);
  
  // Intercept Google OAuth redirects and keep the user on the login screen
  if (
    path.includes('googleusercontent') || 
    path.includes('oauth2redirect') || 
    path.includes('google-login')
  ) {
    console.log('🔐 [NativeIntent] Intercepted Google OAuth redirect, routing to /(tabs)/login');
    return '/(tabs)/login';
  }
  
  return path;
}
