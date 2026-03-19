import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../services/analytics_service.dart';

class CategoriesScreen extends StatefulWidget {
  const CategoriesScreen({super.key});

  @override
  State<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends State<CategoriesScreen> {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  final List<Map<String, dynamic>> _categories = [
    {
      'name': 'الكل',
      'icon': Icons.apps,
      'color': Colors.blue,
      'description': 'جميع الكوبونات المتاحة',
    },
    {
      'name': 'إلكترونيات',
      'icon': Icons.devices,
      'color': Colors.purple,
      'description': 'هواتف، لابتوب، إكسسوارات',
    },
    {
      'name': 'أزياء',
      'icon': Icons.checkroom,
      'color': Colors.pink,
      'description': 'ملابس، أحذية، إكسسوارات',
    },
    {
      'name': 'طعام',
      'icon': Icons.restaurant,
      'color': Colors.orange,
      'description': 'مطاعم، كافيهات، توصيل',
    },
    {
      'name': 'رياضة',
      'icon': Icons.fitness_center,
      'color': Colors.green,
      'description': 'معدات رياضية، ملابس رياضية',
    },
    {
      'name': 'تجميل',
      'icon': Icons.face,
      'color': Colors.red,
      'description': 'عناية بالبشرة، مكياج، عطور',
    },
    {
      'name': 'سفر',
      'icon': Icons.flight,
      'color': Colors.teal,
      'description': 'طيران، فنادق، سيارات',
    },
    {
      'name': 'تعليم',
      'icon': Icons.school,
      'color': Colors.indigo,
      'description': 'دورات، كتب، أدوات تعليمية',
    },
    {
      'name': 'منزل',
      'icon': Icons.home,
      'color': Colors.brown,
      'description': 'أثاث، ديكور، أدوات منزلية',
    },
    {
      'name': 'صحة',
      'icon': Icons.local_hospital,
      'color': Colors.cyan,
      'description': 'صيدليات، عيادات، مستلزمات طبية',
    },
  ];

  Map<String, int> _categoryCounts = {};
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadCategoryCounts();
    AnalyticsService.logScreenView('CategoriesScreen');
  }

  Future<void> _loadCategoryCounts() async {
    try {
      QuerySnapshot snapshot = await _firestore
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now())
          .get();

      Map<String, int> counts = {};
      
      for (var doc in snapshot.docs) {
        final category = doc['category'] as String;
        counts[category] = (counts[category] ?? 0) + 1;
      }
      
      // Count total
      counts['الكل'] = snapshot.docs.length;
      
      setState(() {
        _categoryCounts = counts;
        _isLoading = false;
      });
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('الفئات'),
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadCategoryCounts,
              child: GridView.builder(
                padding: const EdgeInsets.all(16),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  childAspectRatio: 1.2,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                ),
                itemCount: _categories.length,
                itemBuilder: (context, index) {
                  final category = _categories[index];
                  final count = _categoryCounts[category['name']] ?? 0;
                  
                  return Card(
                    elevation: 4,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: InkWell(
                      onTap: () {
                        Navigator.pushNamed(
                          context,
                          '/couponList',
                          arguments: {
                            'title': category['name'],
                            'category': category['name'] == 'الكل' ? null : category['name'],
                          },
                        );
                        AnalyticsService.logCategoryView(category['name']);
                      },
                      borderRadius: BorderRadius.circular(16),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: category['color'].withOpacity(0.1),
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                category['icon'],
                                size: 32,
                                color: category['color'],
                              ),
                            ),
                            const SizedBox(height: 12),
                            Text(
                              category['name'],
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              category['description'],
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: Colors.grey[600],
                              ),
                              textAlign: TextAlign.center,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                              decoration: BoxDecoration(
                                color: category['color'].withOpacity(0.1),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Text(
                                '$count كوبون',
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: category['color'],
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }
}
