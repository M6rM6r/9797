import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';
import '../models/coupon_model.dart';

class CouponActionButtons extends StatefulWidget {
  final Coupon coupon;
  final VoidCallback? onUseCoupon;
  final VoidCallback? onFavoriteToggle;
  final bool isFavorite;

  const CouponActionButtons({
    super.key,
    required this.coupon,
    this.onUseCoupon,
    this.onFavoriteToggle,
    this.isFavorite = false,
  });

  @override
  State<CouponActionButtons> createState() => _CouponActionButtonsState();
}

class _CouponActionButtonsState extends State<CouponActionButtons> {
  bool _isCodeCopied = false;
  bool _isProcessing = false;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Code Section
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.primaryContainer,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: Theme.of(context).colorScheme.primary.withOpacity(0.3),
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.local_offer,
                        color: Theme.of(context).colorScheme.onPrimaryContainer,
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          widget.coupon.code,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: Theme.of(context).colorScheme.onPrimaryContainer,
                            fontWeight: FontWeight.bold,
                            fontFamily: 'monospace',
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 12),
              ElevatedButton.icon(
                onPressed: _isProcessing ? null : _copyCode,
                icon: _isCodeCopied
                    ? const Icon(Icons.check, size: 18)
                    : const Icon(Icons.copy, size: 18),
                label: Text(_isCodeCopied ? 'تم النسخ!' : 'نسخ'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: _isCodeCopied
                      ? Colors.green
                      : Theme.of(context).colorScheme.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                ),
              ),
            ],
          ),

          const SizedBox(height: 16),

          // Action Buttons
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _isProcessing ? null : _useCoupon,
                  icon: _isProcessing
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.shopping_cart_outlined, size: 18),
                  label: Text(_isProcessing ? 'جاري المعالجة...' : 'استخدم الكوبون'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).colorScheme.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              OutlinedButton.icon(
                onPressed: widget.onFavoriteToggle,
                icon: Icon(
                  widget.isFavorite ? Icons.favorite : Icons.favorite_border,
                  size: 18,
                  color: widget.isFavorite ? Colors.red : null,
                ),
                label: Text(widget.isFavorite ? 'مفضل' : 'أضف للمفضلة'),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          // Secondary Actions
          Row(
            children: [
              Expanded(
                child: TextButton.icon(
                  onPressed: _shareCoupon,
                  icon: const Icon(Icons.share_outlined, size: 18),
                  label: const Text('مشاركة'),
                  style: TextButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                  ),
                ),
              ),
              if (widget.coupon.storeUrl.isNotEmpty)
                Expanded(
                  child: TextButton.icon(
                    onPressed: _openStore,
                    icon: const Icon(Icons.store_outlined, size: 18),
                    label: const Text('زيارة المتجر'),
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _copyCode() async {
    try {
      await Clipboard.setData(ClipboardData(text: widget.coupon.code));
      setState(() {
        _isCodeCopied = true;
      });
      
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم نسخ الكود بنجاح'),
          backgroundColor: Colors.green,
        ),
      );
      
      // Reset after 3 seconds
      Future.delayed(const Duration(seconds: 3), () {
        if (mounted) {
          setState(() {
            _isCodeCopied = false;
          });
        }
      });
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('فشل نسخ الكود: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _useCoupon() async {
    setState(() {
      _isProcessing = true;
    });

    try {
      // Copy code first
      await Clipboard.setData(ClipboardData(text: widget.coupon.code));

      // Open store if URL is available
      if (widget.coupon.storeUrl.isNotEmpty) {
        final uri = Uri.parse(widget.coupon.storeUrl);
        if (await canLaunchUrl(uri)) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      }
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('تم نسخ الكود وفتح المتجر'),
            backgroundColor: Colors.green,
          ),
        );
      }
      
      // Call the callback if provided
      widget.onUseCoupon?.call();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('فشل استخدام الكوبون: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isProcessing = false;
        });
      }
    }
  }

  Future<void> _shareCoupon() async {
    try {
      final shareText = '''
🎁 كوبون خصم من ${widget.coupon.storeName}

📝 ${widget.coupon.title}
💰 خصم ${widget.coupon.discountPercent}%
🏷️ الكود: ${widget.coupon.code}
💵 السعر بعد الخصم: ${widget.coupon.discountedPrice} ريال

📱 احصل على التطبيق للمزيد من الكوبونات!
      ''';
      
      await Share.share(shareText);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('فشل المشاركة: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _openStore() async {
    try {
      final uri = Uri.parse(widget.coupon.storeUrl);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('فشل فتح المتجر: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }
}
