import React, { useEffect, useRef, useCallback } from 'react';

interface InfiniteScrollProps {
  children: React.ReactNode;
  loadMore: () => Promise<void>;
  hasMore: boolean;
  loading: boolean;
  threshold?: number;
  reverse?: boolean;
}

export default function InfiniteScroll({
  children,
  loadMore,
  hasMore,
  loading,
  threshold = 100,
  reverse = false
}: InfiniteScrollProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const loadingRef = useRef(false);

  const handleScroll = useCallback(async () => {
    const container = containerRef.current;
    if (!container || loadingRef.current || !hasMore || loading) return;

    const { scrollTop, scrollHeight, clientHeight } = container;
    
    const shouldLoad = reverse 
      ? scrollTop <= threshold
      : scrollHeight - scrollTop - clientHeight <= threshold;

    if (shouldLoad) {
      loadingRef.current = true;
      const prevScrollHeight = scrollHeight;
      
      try {
        await loadMore();
        
        // Maintain scroll position when loading more at the top (reverse scroll)
        if (reverse) {
          requestAnimationFrame(() => {
            const newScrollHeight = container.scrollHeight;
            const scrollDiff = newScrollHeight - prevScrollHeight;
            container.scrollTop = scrollTop + scrollDiff;
          });
        }
      } finally {
        loadingRef.current = false;
      }
    }
  }, [loadMore, hasMore, loading, threshold, reverse]);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    container.addEventListener('scroll', handleScroll, { passive: true });
    
    // Check if we need to load more on mount
    handleScroll();

    return () => {
      container.removeEventListener('scroll', handleScroll);
    };
  }, [handleScroll]);

  return (
    <div
      ref={containerRef}
      className="flex-1 overflow-y-auto"
      style={{
        display: 'flex',
        flexDirection: reverse ? 'column-reverse' : 'column',
      }}
    >
      {reverse && loading && hasMore && (
        <div className="flex justify-center p-4">
          <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
        </div>
      )}
      
      <div className={reverse ? 'flex flex-col-reverse' : 'flex flex-col'}>
        {children}
      </div>
      
      {!reverse && loading && hasMore && (
        <div className="flex justify-center p-4">
          <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
        </div>
      )}
    </div>
  );
}