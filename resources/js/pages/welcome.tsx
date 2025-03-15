import AppLogo from '@/components/app-logo';
import { SearchInput } from '@/components/search-input';
import { Button } from '@/components/ui/button';
import { TextScramble } from '@/components/ui/text-scramble';
import { cn } from '@/lib/utils';
import { WelcomePageProps } from '@/types/welcome';
import { Head, Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronRight, PlayCircle, SearchIcon, Star } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export default function Welcome() {
    const {
        auth,
        name,
        featured: { series, movies },
    } = usePage<WelcomePageProps>().props;
    const isAuthenticated = auth.user !== null;
    const [activeBg, setActiveBg] = useState(0);
    const [isTrigger, setIsTrigger] = useState(false);

    const featuredMedia = useMemo(
        () =>
            [...movies, ...series].map((item) => ({
                num: item.num,
                cover: 'cover' in item ? item.cover : 'stream_icon' in item ? item.stream_icon : undefined,
                name: item.name,
                kind: 'plot' in item ? 'series' : 'movie',
                rating: item.rating_5based,
                description: 'plot' in item ? item.plot : undefined,
                year:
                    'added' in item
                        ? new Date(item.added).getFullYear()
                        : 'last_modified' in item
                          ? new Date(item.last_modified).getFullYear()
                          : 'N/A',
            })),
        [movies, series],
    );

    // Auto-rotate featured backgrounds
    useEffect(() => {
        const interval = setInterval(() => {
            setActiveBg((prev) => (prev + 1) % featuredMedia.length);
        }, 8000);

        return () => clearInterval(interval);
    }, [featuredMedia]);

    // Trigger text scramble effect
    useEffect(() => {
        setIsTrigger(true);
    }, []);

    return (
        <>
            <Head title="Welcome">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>

            <div className="text-primary bg-primary flex min-h-screen flex-col">
                {/* Hero Section with Dynamic Background */}
                <div className="relative h-screen w-full">
                    {/* Dynamic Background Images - Fixed AnimatePresence by removing mode="wait" */}
                    <div className="pointer-events-none absolute inset-0">
                        {featuredMedia.map((bg, index) => (
                            <AnimatePresence key={bg.num}>
                                {index === activeBg && (
                                    <motion.div
                                        className="pointer-events-none absolute inset-0 z-0"
                                        initial={{ opacity: 0 }}
                                        animate={{ opacity: 1 }}
                                        exit={{ opacity: 0 }}
                                        transition={{ duration: 1 }}
                                    >
                                        <div
                                            className="pointer-events-none absolute inset-0 bg-cover bg-center"
                                            style={{ backgroundImage: `url(${bg.cover})` }}
                                        />

                                        {/* Gradient overlay for text readability */}
                                        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black via-black/70 to-black/10" />
                                        <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-black/90 via-black/50 to-transparent" />
                                    </motion.div>
                                )}
                            </AnimatePresence>
                        ))}
                    </div>
                    {/* Header/Navigation - Increased z-index */}
                    <header className="absolute top-0 z-50 w-full">
                        <div className="container mx-auto flex items-center justify-between p-6">
                            <div className="flex items-center">
                                <Link href="/" className="text-2xl font-bold">
                                    <AppLogo></AppLogo>
                                </Link>
                            </div>

                            <nav className="relative z-50 space-x-2">
                                {isAuthenticated ? (
                                    <Button asChild size="lg" className="bg-primary hover:bg-primary/90 gap-2">
                                        <Link href={route('discover')} className="text-primary-foreground">
                                            Discover
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="secondary"
                                            className="border-border text-primary gap-2 bg-white/10 backdrop-blur-md hover:bg-white/20"
                                        >
                                            <Link href={route('login')} className="text-primary">
                                                Log in
                                            </Link>
                                        </Button>
                                        <Button asChild size="lg" className="bg-primary hover:bg-primary/90 gap-2">
                                            <Link href={route('register')} className="text-primary-foreground">
                                                Register
                                            </Link>
                                        </Button>
                                    </>
                                )}
                            </nav>
                        </div>
                    </header>
                    {/* Hero Content - Adjust z-index to be below header */}
                    <div className="pointer-events-none relative z-20 container mx-auto flex h-full flex-col items-start justify-center px-6">
                        {featuredMedia[activeBg] && (
                            <motion.div
                                className="pointer-events-auto max-w-2xl"
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.2, duration: 0.8 }}
                            >
                                <TextScramble
                                    className="text-primary mb-2 font-mono text-lg tracking-widest uppercase"
                                    as="p"
                                    speed={0.01}
                                    trigger={isTrigger}
                                    onHoverStart={() => setIsTrigger(true)}
                                    onScrambleComplete={() => setIsTrigger(false)}
                                >
                                    Unlimited Entertainment
                                </TextScramble>

                                <h1 className="text-primary mb-6 text-4xl leading-tight font-bold md:text-5xl lg:text-6xl">
                                    {featuredMedia[activeBg].name}
                                </h1>

                                <div className="text-muted-foreground mb-4 flex items-center gap-4 text-sm">
                                    <span>{featuredMedia[activeBg].year ?? 'N/A'}</span>
                                    <span className="flex items-center">
                                        <Star className="mr-1 h-4 w-4 text-yellow-500" />
                                        {featuredMedia[activeBg].rating ?? 'N/A'}
                                    </span>
                                </div>

                                <p className="text-muted-foreground mb-8 max-w-lg">
                                    {featuredMedia[activeBg].description}
                                </p>

                                <div className="flex flex-wrap gap-4">
                                    <Button size="lg" className="bg-primary hover:bg-primary/90 gap-2">
                                        <PlayCircle className="h-5 w-5" />
                                        <Link
                                            href={
                                                featuredMedia[activeBg].kind === 'movie'
                                                    ? route('movies.show', { model: featuredMedia[activeBg].num })
                                                    : route('series.show', { model: featuredMedia[activeBg].num })
                                            }
                                            className="text-primary-foreground"
                                        >
                                            Watch Now
                                        </Link>
                                    </Button>

                                    <Button
                                        size="lg"
                                        variant="outline"
                                        className="border-border text-primary gap-2 bg-white/10 backdrop-blur-md hover:bg-white/20"
                                    >
                                        <SearchIcon className="h-5 w-5" />
                                        <Link href={route('discover')} className="text-primary">
                                            Explore Library
                                        </Link>
                                    </Button>
                                </div>
                            </motion.div>
                        )}

                        {/* Background Selector Pills */}
                        <div className="pointer-events-auto absolute bottom-10 left-6 flex gap-2">
                            {featuredMedia.map((_, index) => (
                                <button
                                    key={index}
                                    className={cn(
                                        'h-1 w-8 rounded-full transition-all',
                                        index === activeBg ? 'bg-primary' : 'bg-muted',
                                    )}
                                    onClick={() => setActiveBg(index)}
                                    aria-label={`Select background ${index + 1}`}
                                />
                            ))}
                        </div>
                    </div>
                </div>

                {/* Content Section */}
                <div className="bg-background py-20">
                    <div className="container mx-auto px-6">
                        {isAuthenticated ? <AuthenticatedContent /> : <UnauthenticatedContent name={name} />}
                    </div>
                </div>
            </div>
        </>
    );
}

// Component for authenticated users - shows search and features
function AuthenticatedContent() {
    return (
        <div className="space-y-16">
            {/* Search Section */}
            <motion.div
                className="mx-auto max-w-3xl text-center"
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.3 }}
            >
                <h2 className="text-primary mb-6 text-3xl font-bold">Search Our Entire Library</h2>
                <SearchInput
                    searchRoute="home.search"
                    placeholder="Search movies, TV series..."
                    fullWidth
                    className="shadow-primary/5 shadow-lg"
                />
                <p className="text-muted-foreground mt-4 text-sm">
                    Find your favorite movies and TV shows in our extensive collection
                </p>
            </motion.div>

            {/* Features Grid */}
            <div>
                <h2 className="text-primary mb-8 text-2xl font-bold">Discover Our Features</h2>

                <div className="grid gap-8 md:grid-cols-3">
                    <FeatureCard
                        title="Vast Collection"
                        description="Access thousands of movies and TV shows instantly."
                        icon="🎬"
                        delay={0.3}
                    />
                    <FeatureCard
                        title="High Quality"
                        description="Enjoy your content in HD and 4K with perfect streaming."
                        icon="✨"
                        delay={0.4}
                    />
                    <FeatureCard
                        title="Personalized"
                        description="Get recommendations based on your viewing history."
                        icon="👤"
                        delay={0.5}
                    />
                </div>

                <div className="mt-8 flex justify-center">
                    <Link
                        href={route('discover')}
                        className="group text-primary hover:text-primary/90 inline-flex items-center gap-1 transition"
                    >
                        Browse all content
                        <ChevronRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                    </Link>
                </div>
            </div>
        </div>
    );
}

// Component for unauthenticated users - shows benefits and call-to-action
function UnauthenticatedContent({ name }: { name: string }) {
    return (
        <div className="space-y-16">
            {/* Value Proposition */}
            <motion.div
                className="mx-auto max-w-3xl text-center"
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.3 }}
            >
                <h2 className="text-primary mb-4 text-3xl leading-tight font-bold md:text-4xl lg:text-5xl">
                    Your Premium Entertainment Destination
                </h2>
                <p className="text-muted-foreground mx-auto mb-8 max-w-2xl text-lg">
                    Join {name} today and get access to thousands of movies, TV shows, and exclusive content. Stream
                    anywhere, anytime, on any device.
                </p>
                <Button asChild size="lg" className="bg-primary hover:bg-primary/90 px-8 py-6 text-lg">
                    <Link href={route('register')} className="text-primary-foreground">
                        Start Your Free Trial
                    </Link>
                </Button>
            </motion.div>

            {/* Features Grid */}
            <div>
                <h2 className="text-primary mb-8 text-center text-2xl font-bold">Why Choose {name}?</h2>

                <div className="grid gap-8 md:grid-cols-3">
                    <FeatureCard
                        title="Unlimited Access"
                        description="Stream as much as you want, whenever you want."
                        icon="🔓"
                        delay={0.3}
                    />
                    <FeatureCard
                        title="Premium Quality"
                        description="Enjoy stunning 4K HDR visuals and immersive audio."
                        icon="💎"
                        delay={0.4}
                    />
                    <FeatureCard
                        title="Watch Anywhere"
                        description="Available on smart TVs, tablets, phones, and more."
                        icon="📱"
                        delay={0.5}
                    />
                </div>
            </div>

            {/* Testimonial */}
            <div className="bg-secondary/5 mx-auto max-w-3xl rounded-xl p-8 text-center backdrop-blur-sm">
                <blockquote className="text-secondary-foreground text-lg italic">
                    "{name} offers an incredible streaming experience with a vast library of content. The interface is
                    intuitive and the streaming quality is exceptional."
                </blockquote>
                <p className="text-primary mt-4 font-medium">— Entertainment Weekly</p>
            </div>

            {/* Final CTA */}
            <div className="text-center">
                <p className="text-muted-foreground mb-6 text-lg">Ready to start watching?</p>
                <div className="flex justify-center gap-4">
                    <Button
                        asChild
                        size="lg"
                        variant="outline"
                        className="border-border bg-secondary/10 text-primary hover:bg-secondary/20 backdrop-blur-md"
                    >
                        <Link href={route('login')}>Login</Link>
                    </Button>
                    <Button asChild size="lg" className="bg-primary hover:bg-primary/90">
                        <Link href={route('register')} className="text-primary-foreground">
                            Register
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}

// Reusable Feature Card component
function FeatureCard({
    title,
    description,
    icon,
    delay = 0,
}: {
    title: string;
    description: string;
    icon: string;
    delay?: number;
}) {
    return (
        <motion.div
            className="border-border bg-card rounded-xl border p-6 backdrop-blur-sm"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay, duration: 0.5 }}
        >
            <div className="mb-4 text-3xl">{icon}</div>
            <h3 className="text-primary mb-2 font-bold">{title}</h3>
            <p className="text-card-foreground">{description}</p>
        </motion.div>
    );
}
