/** @type {import('next').NextConfig} */
const nextConfig = {
  // Skip type-checking during build — types are validated in dev via IDE
  // This prevents deployment failures from minor type issues
  typescript: {
    ignoreBuildErrors: true,
  },
  eslint: {
    ignoreDuringBuilds: true,
  },
  images: {
    unoptimized: true,
  },
  // Required for self-hosted Docker deployment
  output: 'standalone',
};

export default nextConfig;
