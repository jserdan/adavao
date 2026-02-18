// Verification service for handling user verification functionality
import { BACKEND_URL } from '../config/backend';

export interface VerificationData {
  userId: string;
  idPicture?: string;
  idSelfie?: string;
  billingDocument?: string;
}

export interface VerificationStatus {
  verification_id: number;
  user_id: number;
  otp_code: string;
  expiration: string;
  status: 'pending' | 'verified' | 'expired' | 'not verified' | 'rejected';
  id_picture?: string;
  id_selfie?: string;
  billing_document?: string;
  is_verified: boolean;
  rejection_reason?: string;
  created_at: string;
  updated_at: string;
}

class VerificationService {
  private apiUrl = `${BACKEND_URL}/api`;

  // Submit verification request
  async submitVerification(data: VerificationData): Promise<{ success: boolean; message: string }> {
    try {
      const response = await fetch(`${this.apiUrl}/verification/submit`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          userId: data.userId,
          idPicture: data.idPicture,
          idSelfie: data.idSelfie,
          billingDocument: data.billingDocument,
        }),
      });

      if (!response.ok) {
        throw new Error(`Failed to submit verification: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error('Error submitting verification:', error);
      return { success: false, message: 'Failed to submit verification' };
    }
  }

  // Get verification status
  async getVerificationStatus(userId: string): Promise<{ success: boolean; data: VerificationStatus | null }> {
    try {
      console.log(`🔍 Fetching verification status for user ID: ${userId}`);
      console.log(`🔗 API URL: ${this.apiUrl}/verification/status/${userId}`);

      const response = await fetch(`${this.apiUrl}/verification/status/${userId}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      console.log(`📊 Verification status response status: ${response.status}`);

      if (!response.ok) {
        console.error(`❌ Failed to fetch verification status: ${response.status} ${response.statusText}`);
        throw new Error(`Failed to fetch verification status: ${response.status}`);
      }

      const result = await response.json();
      console.log('✅ Verification status response:', JSON.stringify(result, null, 2));

      // Ensure status is properly formatted
      if (result.success && result.data) {
        // Normalize status to only allowed values
        const allowedStatuses = ['pending', 'verified', 'expired', 'not verified', 'rejected'];
        if (!allowedStatuses.includes(result.data.status)) {
          result.data.status = result.data.is_verified ? 'verified' : 'not verified';
        }
      }

      return result;
    } catch (error) {
      console.error('💥 Error fetching verification status:', error);
      return { success: false, data: null };
    }
  }

  // Update verification documents
  async updateVerificationDocuments(
    verificationId: number,
    data: Partial<VerificationData>
  ): Promise<{ success: boolean; message: string }> {
    try {
      const response = await fetch(`${this.apiUrl}/verification/${verificationId}/update`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        throw new Error(`Failed to update verification: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error('Error updating verification:', error);
      return { success: false, message: 'Failed to update verification' };
    }
  }
}

export const verificationService = new VerificationService();