package com.smartdesk.backend.controller;

import com.smartdesk.backend.dto.DeskDTO;
import com.smartdesk.backend.dto.ReviewDTO;
import com.smartdesk.backend.model.Desk;
import com.smartdesk.backend.model.Review;
import com.smartdesk.backend.service.HostService;
import com.smartdesk.backend.service.ReviewService;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/host")
public class HostController {

    private final HostService hostService;
    private final ReviewService reviewService;

    public HostController(HostService hostService, ReviewService reviewService) {
        this.hostService = hostService;
        this.reviewService = reviewService;
    }

    @PostMapping("/spaces/{spaceId}/desks")
    public DeskDTO createDesk(@PathVariable Long spaceId, @RequestBody DeskDTO desk) {
        Desk saved = hostService.addDesk(spaceId, desk);
        return new DeskDTO(saved.getDeskId(), saved.getBuilding(), saved.getAmenities());
    }

    @PutMapping("/desks/{deskId}")
    public DeskDTO updateDesk(@PathVariable Long deskId, @RequestBody DeskDTO desk) {
        Desk edited = hostService.editDesk(new DeskDTO(deskId, desk.building(), desk.amenities()));
        return new DeskDTO(edited.getDeskId(), edited.getBuilding(), edited.getAmenities());
    }

    @GetMapping("/{hostId}/reviews")
    public List<ReviewDTO> getMyReviews(@PathVariable Long hostId) {
        List<Review> reviews = reviewService.getReviewsForHost(hostId);
        return reviews.stream().map(r -> new ReviewDTO(r.getHostId(), r.getRating(), r.getSpaceId(), r.getComment())).toList();
    }
}
