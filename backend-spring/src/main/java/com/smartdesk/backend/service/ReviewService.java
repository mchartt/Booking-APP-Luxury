package com.smartdesk.backend.service;

import com.smartdesk.backend.dto.ReviewDTO;
import com.smartdesk.backend.model.Review;
import com.smartdesk.backend.repository.ReviewRepository;
import org.springframework.stereotype.Service;

import java.time.LocalDate;
import java.util.List;

@Service
public class ReviewService {

    private final ReviewRepository reviewRepo;

    public ReviewService(ReviewRepository reviewRepo) {
        this.reviewRepo = reviewRepo;
    }

    public Review leaveReview(Long workerId, ReviewDTO reviewDTO) {
        Review review = new Review();
        review.setWorkerId(workerId);
        review.setHostId(reviewDTO.hostID());
        review.setSpaceId(reviewDTO.spaceID());
        review.setRating(reviewDTO.rating());
        review.setComment(reviewDTO.comment());
        review.setCreatedAt(LocalDate.now());
        return reviewRepo.save(review);
    }

    public List<Review> getReviewsForHost(Long hostId) {
        return reviewRepo.findByHostId(hostId);
    }
}
