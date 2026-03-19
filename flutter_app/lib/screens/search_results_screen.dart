import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../widgets/coupon_card.dart';
import '../services/analytics_service.dart';
import '../models/coupon_model.dart';

class SearchResultsScreen extends StatefulWidget {
  final String initialQuery;
  final String? category;

  const SearchResultsScreen({
    super.key,
    required this.initialQuery,
    this.category,
  });

  @override
  State<SearchResultsScreen> createState() => _SearchResultsScreenState();
}

class _SearchResultsScreenState extends State<SearchResultsScreen> {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  final TextEditingController _searchController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  String _searchQuery = '';
  String _sortBy = 'relevance';
  bool _sortAscending = false;
  bool _isLoading = false;
  DocumentSnapshot? _lastDocument;
  final List<DocumentSnapshot> _results = [];

  @override
  void initState() {
    super.initState();
    _searchQuery = widget.initialQuery;
    _searchController.text = widget.initialQuery;
    _loadSearchResults();
    _setupScrollListener();
    AnalyticsService.logScreenView('SearchResultsScreen');
  }

  void _setupScrollListener() {
    _scrollController.addListener(() {
      if (_scrollController.position.pixels >= 
          _scrollController.position.maxScrollExtent - 200) {
        _loadMoreResults();
      }
    });
  }

  Future<void> _loadSearchResults({bool refresh = false}) async {
    if (refresh) {
      setState(() {
        _isLoading = true;
        _results.clear();
        _lastDocument = null;
      });
    }

    try {
      Query query = _firestore
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now());

      // Apply category filter if provided
      if (widget.category != null && widget.category != 'الكل') {
        query = query.where('category', isEqualTo: widget.category);
      }

      // Apply search filter
      if (_searchQuery.isNotEmpty) {
        query = query
            .where('searchKeywords', arrayContains: _searchQuery.toLowerCase().split(' '))
            .orderBy('usageCount', descending: true);
      } else {
        query = query.orderBy('usageCount', descending: true);
      }

      // Apply sorting
      switch (_sortBy) {
        case 'discount':
          query = query.orderBy('discountPercent', descending: !_sortAscending);
          break;
        case 'expiry':
          query = query.orderBy('expiresAt', descending: !_sortAscending);
          break;
        case 'rating':
          query = query.orderBy('rating', descending: !_sortAscending);
          break;
        default: // relevance
          // Already ordered by usageCount
          break;
      }

      query = query.limit(20);

      if (_lastDocument != null) {
        query = query.startAfterDocument(_lastDocument!);
      }

      QuerySnapshot snapshot = await query.get();
      
      if (mounted) {
        setState(() {
          _results.addAll(snapshot.docs);
          _lastDocument = snapshot.docs.isNotEmpty ? snapshot.docs.last : null;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('حدث خطأ: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _loadMoreResults() async {
    if (_isLoading || _lastDocument == null) return;
    
    setState(() {
      _isLoading = true;
    });
    
    await _loadSearchResults();
  }

  void _onSearchChanged(String query) {
    setState(() {
      _searchQuery = query;
    });
    
    // Debounce search
    Future.delayed(const Duration(milliseconds: 500), () {
      if (mounted && _searchQuery == query) {
        _loadSearchResults(refresh: true);
        AnalyticsService.logSearch(query, _results.length);
      }
    });
  }

  void _showSortOptions() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => Container(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'ترتيب النتائج',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 16),
            RadioListTile<String>(
              title: const Text('الصلة'),
              value: 'relevance',
              groupValue: _sortBy,
              onChanged: (value) {
                setState(() {
                  _sortBy = value!;
                });
                _loadSearchResults(refresh: true);
                Navigator.pop(context);
              },
            ),
            RadioListTile<String>(
              title: const Text('نسبة الخصم'),
              value: 'discount',
              groupValue: _sortBy,
              onChanged: (value) {
                setState(() {
                  _sortBy = value!;
                });
                _loadSearchResults(refresh: true);
                Navigator.pop(context);
              },
            ),
            RadioListTile<String>(
              title: const Text('تاريخ الانتهاء'),
              value: 'expiry',
              groupValue: _sortBy,
              onChanged: (value) {
                setState(() {
                  _sortBy = value!;
                });
                _loadSearchResults(refresh: true);
                Navigator.pop(context);
              },
            ),
            RadioListTile<String>(
              title: const Text('التقييم'),
              value: 'rating',
              groupValue: _sortBy,
              onChanged: (value) {
                setState(() {
                  _sortBy = value!;
                });
                _loadSearchResults(refresh: true);
                Navigator.pop(context);
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('بحث'),
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: Icon(_sortAscending ? Icons.arrow_upward : Icons.arrow_downward),
            onPressed: () {
              setState(() {
                _sortAscending = !_sortAscending;
              });
              _loadSearchResults(refresh: true);
            },
          ),
          IconButton(
            icon: const Icon(Icons.sort),
            onPressed: _showSortOptions,
          ),
        ],
      ),
      body: Column(
        children: [
          // Search Bar
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'ابحث عن كوبونات...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchQuery.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          _onSearchChanged('');
                        },
                      )
                    : null,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                filled: true,
                fillColor: Colors.grey[50],
              ),
              textDirection: TextDirection.rtl,
            ),
          ),

          // Search Info
          if (_searchQuery.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16.0),
              child: Row(
                children: [
                  Text(
                    'نتائج البحث عن: "$_searchQuery"',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.grey[600],
                    ),
                  ),
                  const Spacer(),
                  Text(
                    '${_results.length} نتيجة',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Theme.of(context).colorScheme.primary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),

          // Results
          Expanded(
            child: _results.isEmpty && !_isLoading
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.search_off,
                          size: 64,
                          color: Colors.grey[400],
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'لا توجد نتائج للبحث',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: Colors.grey[600],
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'جرب كلمات مفتاحية مختلفة',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Colors.grey[500],
                          ),
                        ),
                      ],
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: () => _loadSearchResults(refresh: true),
                    child: GridView.builder(
                      controller: _scrollController,
                      padding: const EdgeInsets.all(16),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        childAspectRatio: 0.7,
                        crossAxisSpacing: 8,
                        mainAxisSpacing: 8,
                      ),
                      itemCount: _results.length,
                      itemBuilder: (context, index) {
                        final doc = _results[index];
                        final coupon = Coupon.fromFirestore(doc);
                        
                        return CouponCard(
                          coupon: coupon,
                          onTap: () {
                            Navigator.pushNamed(
                              context,
                              '/coupon',
                              arguments: {
                                'couponId': doc.id,
                                'coupon': coupon,
                              },
                            );
                          },
                        );
                      },
                    ),
                  ),
          ),

          // Loading Indicator
          if (_isLoading)
            const Padding(
              padding: EdgeInsets.all(16.0),
              child: CircularProgressIndicator(),
            ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }
}
