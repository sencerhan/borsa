export type OrderSide = 'buy' | 'sell';
export type OrderType = 'limit' | 'market';
export type OrderStatus = 'open' | 'partially_filled' | 'filled' | 'cancelled';
export type TransferStatus = 'pending' | 'completed' | 'failed';

export interface Market {
    id: number;
    symbol: string;
    base_asset: string;
    quote_asset: string;
    maker_fee: string;
    taker_fee: string;
    min_order_size: string;
    price_scale: number;
    size_scale: number;
    last_price: string | null;
    is_active: boolean;
}

export interface Asset {
    id: number;
    symbol: string;
    name: string;
    network: string | null;
    scale: number;
}

export interface Wallet {
    id: number;
    asset: string;
    deposit_address: string | null;
    available: string;
    locked: string;
}

export interface OrderBookLevel {
    price: string;
    quantity: string;
    total: string;
}

export interface OrderBook {
    bids: OrderBookLevel[];
    asks: OrderBookLevel[];
}

export interface Ticker {
    last_price: string | null;
    high: string | null;
    low: string | null;
    volume: string;
    change_percent: string | null;
}

export interface Order {
    id: number;
    market_id: number;
    side: OrderSide;
    type: OrderType;
    status: OrderStatus;
    price: string | null;
    quantity: string;
    filled_quantity: string;
    quote_amount: string | null;
    quote_filled: string;
    created_at: string;
}

export interface Trade {
    id: number;
    price: string;
    quantity: string;
    quote_amount: string;
    taker_side: OrderSide;
    created_at: string;
}

export interface Deposit {
    id: number;
    asset: string;
    amount: string;
    status: TransferStatus;
    reference: string | null;
    created_at: string;
}

export interface Withdrawal {
    id: number;
    asset: string;
    amount: string;
    address: string | null;
    status: TransferStatus;
    reference: string | null;
    created_at: string;
}
