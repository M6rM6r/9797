import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../services/analytics_service.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  String _userId = 'user123'; // TODO: Get from auth
  Map<String, dynamic>? _userData;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadUserData();
    AnalyticsService.logScreenView('ProfileScreen');
  }

  Future<void> _loadUserData() async {
    try {
      DocumentSnapshot userDoc = await _firestore
          .collection('users')
          .doc(_userId)
          .get();

      if (userDoc.exists) {
        setState(() {
          _userData = userDoc.data() as Map<String, dynamic>;
          _isLoading = false;
        });
      } else {
        // Create default user data
        await _firestore.collection('users').doc(_userId).set({
          'name': 'مستخدم جديد',
          'email': 'user@example.com',
          'joinedAt': Timestamp.now(),
          'totalSavings': 0,
          'couponsUsed': 0,
          'favoriteCategories': [],
          'notificationSettings': {
            'push': true,
            'email': true,
            'newCoupons': true,
            'expiringCoupons': true,
          },
        });

        setState(() {
          _userData = {
            'name': 'مستخدم جديد',
            'email': 'user@example.com',
            'joinedAt': Timestamp.now(),
            'totalSavings': 0,
            'couponsUsed': 0,
            'favoriteCategories': [],
            'notificationSettings': {
              'push': true,
              'email': true,
              'newCoupons': true,
              'expiringCoupons': true,
            },
          };
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('حدث خطأ: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _updateUserData(String field, dynamic value) async {
    try {
      await _firestore.collection('users').doc(_userId).update({
        field: value,
      });

      setState(() {
        _userData![field] = value;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم تحديث المعلومات بنجاح'),
          backgroundColor: Colors.green,
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('حدث خطأ: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void _showEditNameDialog() {
    final controller = TextEditingController(text: _userData!['name']);
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('تعديل الاسم'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(
            labelText: 'الاسم',
            border: OutlineInputBorder(),
          ),
          textDirection: TextDirection.rtl,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('إلغاء'),
          ),
          ElevatedButton(
            onPressed: () {
              if (controller.text.trim().isNotEmpty) {
                _updateUserData('name', controller.text.trim());
                Navigator.pop(context);
              }
            },
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
  }

  void _showNotificationSettings() {
    final settings = _userData!['notificationSettings'] as Map<String, dynamic>;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('إعدادات الإشعارات'),
        content: StatefulBuilder(
          builder: (context, setState) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SwitchListTile(
                title: const Text('الإشعارات الفورية'),
                subtitle: const Text('تلقي إشعارات فورية على الجهاز'),
                value: settings['push'] ?? true,
                onChanged: (value) {
                  setState(() {
                    settings['push'] = value;
                  });
                },
              ),
              SwitchListTile(
                title: const Text('البريد الإلكتروني'),
                subtitle: const Text('تلقي الإشعارات عبر البريد الإلكتروني'),
                value: settings['email'] ?? true,
                onChanged: (value) {
                  setState(() {
                    settings['email'] = value;
                  });
                },
              ),
              SwitchListTile(
                title: const Text('كوبونات جديدة'),
                subtitle: const Text('إشعارات عند إضافة كوبونات جديدة'),
                value: settings['newCoupons'] ?? true,
                onChanged: (value) {
                  setState(() {
                    settings['newCoupons'] = value;
                  });
                },
              ),
              SwitchListTile(
                title: const Text('الكوبونات المنتهية'),
                subtitle: const Text('إشعارات قبل انتهاء صلاحية الكوبونات'),
                value: settings['expiringCoupons'] ?? true,
                onChanged: (value) {
                  setState(() {
                    settings['expiringCoupons'] = value;
                  });
                },
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('إلغاء'),
          ),
          ElevatedButton(
            onPressed: () {
              _updateUserData('notificationSettings', settings);
              Navigator.pop(context);
            },
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('الملف الشخصي'),
          backgroundColor: Theme.of(context).colorScheme.primary,
          foregroundColor: Colors.white,
        ),
        body: const Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('الملف الشخصي'),
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.settings),
            onPressed: _showNotificationSettings,
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Profile Header
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 40,
                      backgroundColor: Theme.of(context).colorScheme.primary,
                      child: Text(
                        _userData!['name'][0].toUpperCase(),
                        style: const TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _userData!['name'],
                            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            _userData!['email'],
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: Colors.grey[600],
                            ),
                          ),
                          Text(
                            'انضم في ${_formatDate(_userData!['joinedAt'])}',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Colors.grey[500],
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.edit),
                      onPressed: _showEditNameDialog,
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Statistics
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'إحصائياتي',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: _buildStatCard(
                            context,
                            'إجمالي التوفير',
                            '${_userData!['totalSavings']} ريال',
                            Icons.savings,
                            Colors.green,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _buildStatCard(
                            context,
                            'الكوبونات المستخدمة',
                            '${_userData!['couponsUsed']}',
                            Icons.local_offer,
                            Colors.blue,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Favorite Categories
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'الفئات المفضلة',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: (_userData!['favoriteCategories'] as List<dynamic>? ?? [])
                          .map<Widget>((category) => Chip(
                                label: Text(category),
                                backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                              ))
                          .toList(),
                    ),
                    if ((_userData!['favoriteCategories'] as List<dynamic>?)?.isEmpty ?? true)
                      Text(
                        'لا توجد فئات مفضلة بعد',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Colors.grey[600],
                        ),
                      ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Quick Actions
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'إجراءات سريعة',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    ListTile(
                      leading: const Icon(Icons.history),
                      title: const Text('سجل الكوبونات'),
                      trailing: const Icon(Icons.arrow_back_ios),
                      onTap: () {
                        // TODO: Navigate to coupon history
                      },
                    ),
                    ListTile(
                      leading: const Icon(Icons.share),
                      title: const Text('مشاركة التطبيق'),
                      trailing: const Icon(Icons.arrow_back_ios),
                      onTap: () {
                        // TODO: Share app
                      },
                    ),
                    ListTile(
                      leading: const Icon(Icons.rate_review),
                      title: const Text('تقييم التطبيق'),
                      trailing: const Icon(Icons.arrow_back_ios),
                      onTap: () {
                        // TODO: Rate app
                      },
                    ),
                    ListTile(
                      leading: const Icon(Icons.help_outline),
                      title: const Text('المساعدة والدعم'),
                      trailing: const Icon(Icons.arrow_back_ios),
                      onTap: () {
                        // TODO: Help and support
                      },
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatCard(BuildContext context, String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Column(
        children: [
          Icon(
            icon,
            color: color,
            size: 24,
          ),
          const SizedBox(height: 8),
          Text(
            value,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
          Text(
            title,
            style: Theme.of(context).textTheme.bodySmall,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  String _formatDate(Timestamp timestamp) {
    DateTime date = timestamp.toDate();
    return '${date.day}/${date.month}/${date.year}';
  }
}
