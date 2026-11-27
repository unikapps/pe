export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

/** Money is always an exact decimal dollar string, e.g. "0.381887500". */
export type Dollars = string;

export type Generation = {
    id: string;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    status: GenerationStatus;
    is_final: boolean;
    estimated_price: Dollars;
    estimated_price_max: Dollars;
    price: Dollars | null;
    image_url: string | null;
    error: string | null;
    created_at: string;
    completed_at: string | null;
};

export type Estimate = {
    min: Dollars;
    expected: Dollars;
    max: Dollars;
};
