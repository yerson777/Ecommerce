export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T | null;
}

export interface ApiError {
  success: boolean;
  message: string;
  errors: Record<string, string[]> | null;
  status?: number;
}