<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use EthereumPHP\Ethereum;
use EthereumPHP\Contract;
use Web3\Web3;
use Web3\Contract as Web3Contract;
use Web3\Providers\HttpProvider;
use Web3\Utils;
use Web3p\EthereumUtil\Util;

class BlockchainService
{
    protected Ethereum $ethereum;
    protected Web3 $web3;
    protected array $contracts = [];
    protected string $privateKey;
    protected string $publicAddress;
    protected array $smartContracts = [
        'coupon_registry' => [
            'address' => null,
            'abi' => null,
            'bytecode' => null,
        ],
        'affiliate_payment' => [
            'address' => null,
            'abi' => null,
            'bytecode' => null,
        ],
        'user_rewards' => [
            'address' => null,
            'abi' => null,
            'bytecode' => null,
        ],
        'coupon_authenticity' => [
            'address' => null,
            'abi' => null,
            'bytecode' => null,
        ],
    ];

    public function __construct()
    {
        $this->initializeBlockchain();
        $this->loadContracts();
    }

    /**
     * Initialize blockchain connection
     */
    protected function initializeBlockchain(): void
    {
        try {
            // Ethereum connection
            $this->ethereum = new Ethereum('https://mainnet.infura.io/v3/' . config('services.infura.project_id'));
            
            // Web3 connection
            $this->web3 = new Web3(new HttpProvider(config('blockchain.rpc_url')));
            
            // Set up account
            $this->privateKey = config('blockchain.private_key');
            $this->publicAddress = $this->getPublicAddressFromPrivateKey($this->privateKey);
            
            Log::info('Blockchain service initialized', [
                'network' => config('blockchain.network'),
                'address' => $this->publicAddress,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Blockchain initialization failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Deploy smart contracts
     */
    public function deployContracts(): array
    {
        $deployedContracts = [];
        
        try {
            // Deploy Coupon Registry Contract
            $couponRegistry = $this->deployCouponRegistryContract();
            $deployedContracts['coupon_registry'] = $couponRegistry;
            
            // Deploy Affiliate Payment Contract
            $affiliatePayment = $this->deployAffiliatePaymentContract();
            $deployedContracts['affiliate_payment'] = $affiliatePayment;
            
            // Deploy User Rewards Contract
            $userRewards = $this->deployUserRewardsContract();
            $deployedContracts['user_rewards'] = $userRewards;
            
            // Deploy Coupon Authenticity Contract
            $couponAuthenticity = $this->deployCouponAuthenticityContract();
            $deployedContracts['coupon_authenticity'] = $couponAuthenticity;
            
            // Save deployed addresses
            $this->saveDeployedContracts($deployedContracts);
            
            Log::info('Smart contracts deployed successfully', $deployedContracts);
            
            return $deployedContracts;
            
        } catch (\Exception $e) {
            Log::error('Contract deployment failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Deploy Coupon Registry Contract
     */
    protected function deployCouponRegistryContract(): array
    {
        $contractCode = $this->getCouponRegistryBytecode();
        $abi = $this->getCouponRegistryABI();
        
        // Deploy contract
        $transaction = [
            'from' => $this->publicAddress,
            'gas' => '0x200000',
            'gasPrice' => '0x3B9ACA00', // 1 Gwei
            'data' => '0x' . $contractCode,
        ];
        
        $result = $this->ethereum->eth()->sendTransaction($transaction);
        $contractAddress = $result['contractAddress'];
        
        return [
            'address' => $contractAddress,
            'abi' => $abi,
            'transaction_hash' => $result['transactionHash'],
            'block_number' => $result['blockNumber'],
        ];
    }

    /**
     * Deploy Affiliate Payment Contract
     */
    protected function deployAffiliatePaymentContract(): array
    {
        $contractCode = $this->getAffiliatePaymentBytecode();
        $abi = $this->getAffiliatePaymentABI();
        
        $transaction = [
            'from' => $this->publicAddress,
            'gas' => '0x200000',
            'gasPrice' => '0x3B9ACA00',
            'data' => '0x' . $contractCode,
        ];
        
        $result = $this->ethereum->eth()->sendTransaction($transaction);
        
        return [
            'address' => $result['contractAddress'],
            'abi' => $abi,
            'transaction_hash' => $result['transactionHash'],
            'block_number' => $result['blockNumber'],
        ];
    }

    /**
     * Deploy User Rewards Contract
     */
    protected function deployUserRewardsContract(): array
    {
        $contractCode = $this->getUserRewardsBytecode();
        $abi = $this->getUserRewardsABI();
        
        $transaction = [
            'from' => $this->publicAddress,
            'gas' => '0x200000',
            'gasPrice' => '0x3B9ACA00',
            'data' => '0x' . $contractCode,
        ];
        
        $result = $this->ethereum->eth()->sendTransaction($transaction);
        
        return [
            'address' => $result['contractAddress'],
            'abi' => $abi,
            'transaction_hash' => $result['transactionHash'],
            'block_number' => $result['blockNumber'],
        ];
    }

    /**
     * Deploy Coupon Authenticity Contract
     */
    protected function deployCouponAuthenticityContract(): array
    {
        $contractCode = $this->getCouponAuthenticityBytecode();
        $abi = $this->getCouponAuthenticityABI();
        
        $transaction = [
            'from' => $this->publicAddress,
            'gas' => '0x200000',
            'gasPrice' => '0x3B9ACA00',
            'data' => '0x' . $contractCode,
        ];
        
        $result = $this->ethereum->eth()->sendTransaction($transaction);
        
        return [
            'address' => $result['contractAddress'],
            'abi' => $abi,
            'transaction_hash' => $result['transactionHash'],
            'block_number' => $result['blockNumber'],
        ];
    }

    /**
     * Register coupon on blockchain
     */
    public function registerCoupon(array $couponData): array
    {
        try {
            $contractAddress = $this->smartContracts['coupon_registry']['address'];
            $abi = $this->smartContracts['coupon_registry']['abi'];
            
            $contract = new Contract($this->web3->provider, $abi, $contractAddress);
            
            // Prepare transaction data
            $transactionData = [
                'couponId' => $couponData['id'],
                'code' => $couponData['code'],
                'storeId' => $couponData['store_id'],
                'discountPercent' => $couponData['discount_percent'],
                'expiresAt' => strtotime($couponData['expires_at']),
                'isVerified' => $couponData['is_verified'],
                'hash' => $this->generateCouponHash($couponData),
            ];
            
            // Create transaction
            $transaction = $contract->send('registerCoupon', [
                $transactionData['couponId'],
                $transactionData['code'],
                $transactionData['storeId'],
                $transactionData['discountPercent'],
                $transactionData['expiresAt'],
                $transactionData['isVerified'],
                $transactionData['hash'],
            ], [
                'from' => $this->publicAddress,
                'gas' => '0x100000',
                'gasPrice' => '0x3B9ACA00',
            ]);
            
            // Wait for confirmation
            $receipt = $this->web3->eth->getTransactionReceipt($transaction->transactionHash);
            
            // Log the registration
            Log::info('Coupon registered on blockchain', [
                'coupon_id' => $couponData['id'],
                'transaction_hash' => $transaction->transactionHash,
                'block_number' => $receipt->blockNumber,
            ]);
            
            return [
                'success' => true,
                'transaction_hash' => $transaction->transactionHash,
                'block_number' => $receipt->blockNumber,
                'gas_used' => $receipt->gasUsed,
                'contract_address' => $contractAddress,
            ];
            
        } catch (\Exception $e) {
            Log::error('Coupon registration failed', [
                'coupon_id' => $couponData['id'],
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify coupon authenticity on blockchain
     */
    public function verifyCouponAuthenticity(string $couponId): array
    {
        try {
            $contractAddress = $this->smartContracts['coupon_authenticity']['address'];
            $abi = $this->smartContracts['coupon_authenticity']['abi'];
            
            $contract = new Contract($this->web3->provider, $abi, $contractAddress);
            
            // Call verification function
            $result = $contract->call('verifyCoupon', [$couponId]);
            
            return [
                'is_authentic' => $result[0],
                'verification_hash' => $result[1],
                'registration_timestamp' => $result[2],
                'verified_by' => $result[3],
                'block_number' => $result[4],
            ];
            
        } catch (\Exception $e) {
            Log::error('Coupon verification failed', [
                'coupon_id' => $couponId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'is_authentic' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process affiliate payment through smart contract
     */
    public function processAffiliatePayment(array $paymentData): array
    {
        try {
            $contractAddress = $this->smartContracts['affiliate_payment']['address'];
            $abi = $this->smartContracts['affiliate_payment']['abi'];
            
            $contract = new Contract($this->web3->provider, $abi, $contractAddress);
            
            // Convert amounts to wei
            $amountWei = Utils::toWei($paymentData['amount'], 'ether');
            
            // Create payment transaction
            $transaction = $contract->send('processPayment', [
                $paymentData['affiliate_id'],
                $paymentData['coupon_id'],
                $amountWei,
                $paymentData['currency'],
                $paymentData['timestamp'],
            ], [
                'from' => $this->publicAddress,
                'gas' => '0x150000',
                'gasPrice' => '0x3B9ACA00',
                'value' => $amountWei,
            ]);
            
            // Wait for confirmation
            $receipt = $this->web3->eth->getTransactionReceipt($transaction->transactionHash);
            
            // Log payment processing
            Log::info('Affiliate payment processed on blockchain', [
                'affiliate_id' => $paymentData['affiliate_id'],
                'amount' => $paymentData['amount'],
                'transaction_hash' => $transaction->transactionHash,
                'block_number' => $receipt->blockNumber,
            ]);
            
            return [
                'success' => true,
                'transaction_hash' => $transaction->transactionHash,
                'block_number' => $receipt->blockNumber,
                'gas_used' => $receipt->gasUsed,
                'payment_id' => $receipt->logs[0]->topics[1] ?? null,
            ];
            
        } catch (\Exception $e) {
            Log::error('Affiliate payment processing failed', [
                'affiliate_id' => $paymentData['affiliate_id'],
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Distribute user rewards through smart contract
     */
    public function distributeUserRewards(array $rewardData): array
    {
        try {
            $contractAddress = $this->smartContracts['user_rewards']['address'];
            $abi = $this->smartContracts['user_rewards']['abi'];
            
            $contract = new Contract($this->web3->provider, $abi, $contractAddress);
            
            // Convert reward amount to wei
            $rewardWei = Utils::toWei($rewardData['amount'], 'ether');
            
            // Create reward transaction
            $transaction = $contract->send('distributeReward', [
                $rewardData['user_id'],
                $rewardData['reward_type'],
                $rewardWei,
                $rewardData['reason'],
                $rewardData['timestamp'],
            ], [
                'from' => $this->publicAddress,
                'gas' => '0x120000',
                'gasPrice' => '0x3B9ACA00',
                'value' => $rewardWei,
            ]);
            
            // Wait for confirmation
            $receipt = $this->web3->eth->getTransactionReceipt($transaction->transactionHash);
            
            // Log reward distribution
            Log::info('User reward distributed on blockchain', [
                'user_id' => $rewardData['user_id'],
                'amount' => $rewardData['amount'],
                'reward_type' => $rewardData['reward_type'],
                'transaction_hash' => $transaction->transactionHash,
                'block_number' => $receipt->blockNumber,
            ]);
            
            return [
                'success' => true,
                'transaction_hash' => $transaction->transactionHash,
                'block_number' => $receipt->blockNumber,
                'gas_used' => $receipt->gasUsed,
                'reward_id' => $receipt->logs[0]->topics[1] ?? null,
            ];
            
        } catch (\Exception $e) {
            Log::error('User reward distribution failed', [
                'user_id' => $rewardData['user_id'],
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get blockchain statistics
     */
    public function getBlockchainStatistics(): array
    {
        try {
            $stats = [
                'network' => config('blockchain.network'),
                'gas_price' => $this->web3->eth->gasPrice(),
                'block_number' => $this->web3->eth->blockNumber(),
                'total_transactions' => 0,
                'contract_addresses' => [],
                'total_coupons_registered' => 0,
                'total_payments_processed' => 0,
                'total_rewards_distributed' => 0,
                'average_gas_used' => 0,
                'network_utilization' => 0,
            ];
            
            // Get contract statistics
            foreach ($this->smartContracts as $contractName => $contractData) {
                if ($contractData['address']) {
                    $stats['contract_addresses'][$contractName] = $contractData['address'];
                    
                    // Get contract-specific statistics
                    $contractStats = $this->getContractStatistics($contractName);
                    $stats = array_merge($stats, $contractStats);
                }
            }
            
            // Calculate network utilization
            $latestBlock = $this->web3->eth->getBlock('latest');
            $stats['network_utilization'] = $this->calculateNetworkUtilization($latestBlock);
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error('Failed to get blockchain statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get contract-specific statistics
     */
    protected function getContractStatistics(string $contractName): array
    {
        try {
            $contractAddress = $this->smartContracts[$contractName]['address'];
            $abi = $this->smartContracts[$contractName]['abi'];
            
            $contract = new Contract($this->web3->provider, $abi, $contractAddress);
            
            $stats = [];
            
            switch ($contractName) {
                case 'coupon_registry':
                    $stats['total_coupons_registered'] = $contract->call('getTotalCoupons')[0];
                    $stats['verified_coupons'] = $contract->call('getVerifiedCoupons')[0];
                    break;
                    
                case 'affiliate_payment':
                    $stats['total_payments_processed'] = $contract->call('getTotalPayments')[0];
                    $stats['total_amount_paid'] = Utils::fromWei($contract->call('getTotalAmountPaid')[0], 'ether');
                    break;
                    
                case 'user_rewards':
                    $stats['total_rewards_distributed'] = $contract->call('getTotalRewards')[0];
                    $stats['total_rewards_amount'] = Utils::fromWei($contract->call('getTotalRewardAmount')[0], 'ether');
                    break;
                    
                case 'coupon_authenticity':
                    $stats['verified_coupons'] = $contract->call('getVerifiedCoupons')[0];
                    $stats['authentic_coupons'] = $contract->call('getAuthenticCoupons')[0];
                    break;
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error("Failed to get contract statistics for {$contractName}", [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Calculate network utilization
     */
    protected function calculateNetworkUtilization($latestBlock): float
    {
        try {
            $gasLimit = $latestBlock->gasLimit;
            $gasUsed = $latestBlock->gasUsed;
            
            return $gasLimit > 0 ? ($gasUsed / $gasLimit) * 100 : 0.0;
            
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Generate coupon hash for blockchain verification
     */
    protected function generateCouponHash(array $couponData): string
    {
        $hashData = [
            $couponData['id'],
            $couponData['code'],
            $couponData['store_id'],
            $couponData['discount_percent'],
            $couponData['expires_at'],
            $couponData['is_verified'],
        ];
        
        return hash('sha256', json_encode($hashData));
    }

    /**
     * Get public address from private key
     */
    protected function getPublicAddressFromPrivateKey(string $privateKey): string
    {
        return Util::publicKey($privateKey);
    }

    /**
     * Load deployed contracts from cache
     */
    protected function loadContracts(): void
    {
        try {
            $cachedContracts = Cache::get('blockchain_contracts');
            
            if ($cachedContracts) {
                $this->smartContracts = array_merge($this->smartContracts, $cachedContracts);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to load cached contracts', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Save deployed contracts to cache
     */
    protected function saveDeployedContracts(array $contracts): void
    {
        try {
            Cache::put('blockchain_contracts', $contracts, 86400); // 24 hours
            
        } catch (\Exception $e) {
            Log::error('Failed to save deployed contracts', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get coupon registry ABI
     */
    protected function getCouponRegistryABI(): string
    {
        return '[
            {
                "constant": false,
                "inputs": [
                    {"name": "couponId", "type": "string"},
                    {"name": "code", "type": "string"},
                    {"name": "storeId", "type": "string"},
                    {"name": "discountPercent", "type": "uint256"},
                    {"name": "expiresAt", "type": "uint256"},
                    {"name": "isVerified", "type": "bool"},
                    {"name": "hash", "type": "bytes32"}
                ],
                "name": "registerCoupon",
                "outputs": [],
                "payable": false,
                "stateMutability": "nonpayable",
                "type": "function"
            },
            {
                "constant": true,
                "inputs": [{"name": "couponId", "type": "string"}],
                "name": "getCoupon",
                "outputs": [
                    {"name": "code", "type": "string"},
                    {"name": "storeId", "type": "string"},
                    {"name": "discountPercent", "type": "uint256"},
                    {"name": "expiresAt", "type": "uint256"},
                    {"name": "isVerified", "type": "bool"},
                    {"name": "hash", "type": "bytes32"},
                    {"name": "registeredAt", "type": "uint256"}
                ],
                "payable": false,
                "stateMutability": "view",
                "type": "function"
            }
        ]';
    }

    /**
     * Get coupon registry bytecode
     */
    protected function getCouponRegistryBytecode(): string
    {
        // This would be the compiled bytecode from Solidity
        // For now, return a placeholder
        return '608060405234801561001057600080fd5b506000806101000a0000a165627a7a72305820';
    }

    /**
     * Get affiliate payment ABI
     */
    protected function getAffiliatePaymentABI(): string
    {
        return '[
            {
                "constant": false,
                "inputs": [
                    {"name": "affiliateId", "type": "string"},
                    {"name": "couponId", "type": "string"},
                    {"name": "amount", "type": "uint256"},
                    {"name": "currency", "type": "string"},
                    {"name": "timestamp", "type": "uint256"}
                ],
                "name": "processPayment",
                "outputs": [],
                "payable": true,
                "stateMutability": "payable",
                "type": "function"
            }
        ]';
    }

    /**
     * Get affiliate payment bytecode
     */
    protected function getAffiliatePaymentBytecode(): string
    {
        return '608060405234801561001057600080fd5b506000806101000a0000a165627a7a72305820';
    }

    /**
     * Get user rewards ABI
     */
    protected function getUserRewardsABI(): string
    {
        return '[
            {
                "constant": false,
                "inputs": [
                    {"name": "userId", "type": "string"},
                    {"name": "rewardType", "type": "string"},
                    {"name": "amount", "type": "uint256"},
                    {"name": "reason", "type": "string"},
                    {"name": "timestamp", "type": "uint256"}
                ],
                "name": "distributeReward",
                "outputs": [],
                "payable": true,
                "stateMutability": "payable",
                "type": "function"
            }
        ]';
    }

    /**
     * Get user rewards bytecode
     */
    protected function getUserRewardsBytecode(): string
    {
        return '608060405234801561001057600080fd5b506000806101000a0000a165627a7a72305820';
    }

    /**
     * Get coupon authenticity ABI
     */
    protected function getCouponAuthenticityABI(): string
    {
        return '[
            {
                "constant": true,
                "inputs": [{"name": "couponId", "type": "string"}],
                "name": "verifyCoupon",
                "outputs": [
                    {"name": "isAuthentic", "type": "bool"},
                    {"name": "verificationHash", "type": "bytes32"},
                    {"name": "registrationTimestamp", "type": "uint256"},
                    {"name": "verifiedBy", "type": "address"},
                    {"name": "blockNumber", "type": "uint256"}
                ],
                "payable": false,
                "stateMutability": "view",
                "type": "function"
            }
        ]';
    }

    /**
     * Get coupon authenticity bytecode
     */
    protected function getCouponAuthenticityBytecode(): string
    {
        return '608060405234801561001057600080fd5b506000806101000a0000a165627a7a72305820';
    }

    /**
     * Monitor blockchain events
     */
    public function monitorBlockchainEvents(): array
    {
        try {
            $events = [];
            
            foreach ($this->smartContracts as $contractName => $contractData) {
                if ($contractData['address']) {
                    $contractEvents = $this->getContractEvents($contractName);
                    $events[$contractName] = $contractEvents;
                }
            }
            
            return $events;
            
        } catch (\Exception $e) {
            Log::error('Failed to monitor blockchain events', [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Get events for a specific contract
     */
    protected function getContractEvents(string $contractName): array
    {
        try {
            $contractAddress = $this->smartContracts[$contractName]['address'];
            $abi = $this->smartContracts[$contractName]['abi'];
            
            $contract = new Contract($this->web3->provider, $abi, $contractAddress);
            
            // Get recent events
            $latestBlock = $this->web3->eth->blockNumber();
            $fromBlock = $latestBlock - 100; // Last 100 blocks
            
            $events = $contract->getEvents([], [
                'fromBlock' => $fromBlock,
                'toBlock' => 'latest',
            ]);
            
            return $events;
            
        } catch (\Exception $e) {
            Log::error("Failed to get events for {$contractName}", [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Get transaction details
     */
    public function getTransactionDetails(string $transactionHash): array
    {
        try {
            $transaction = $this->web3->eth->getTransaction($transactionHash);
            $receipt = $this->web3->eth->getTransactionReceipt($transactionHash);
            
            return [
                'transaction' => $transaction,
                'receipt' => $receipt,
                'status' => $receipt->status,
                'gas_used' => $receipt->gasUsed,
                'block_number' => $receipt->blockNumber,
                'logs' => $receipt->logs,
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get transaction details', [
                'transaction_hash' => $transactionHash,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get wallet balance
     */
    public function getWalletBalance(): array
    {
        try {
            $balanceWei = $this->web3->eth->getBalance($this->publicAddress);
            $balanceEther = Utils::fromWei($balanceWei, 'ether');
            
            return [
                'address' => $this->publicAddress,
                'balance_wei' => $balanceWei,
                'balance_ether' => $balanceEther,
                'balance_usd' => $this->convertWeiToUSD($balanceWei),
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get wallet balance', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert Wei to USD
     */
    protected function convertWeiToUSD(string $weiAmount): float
    {
        try {
            $etherAmount = Utils::fromWei($weiAmount, 'ether');
            $ethPrice = $this->getETHPrice(); // Get current ETH price in USD
            
            return $etherAmount * $ethPrice;
            
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get current ETH price
     */
    protected function getETHPrice(): float
    {
        try {
            // This would fetch from a price API
            // For now, return a placeholder
            return 2000.0; // $2000 per ETH
        } catch (\Exception $e) {
            return 2000.0;
        }
    }
}
